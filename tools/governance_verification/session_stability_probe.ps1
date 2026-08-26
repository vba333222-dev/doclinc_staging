param(
    [Parameter(Mandatory=$true)][string]$BaseUrl,
    [Parameter(Mandatory=$true)][string]$PhpContainer,
    [Parameter(Mandatory=$true)][string]$AdminEmail,
    [Parameter(Mandatory=$true)][string]$AdminPassword,
    [Parameter(Mandatory=$true)][string]$OutputPath
)
$ErrorActionPreference = 'Stop'

function Hash-Text([string]$value) {
    $bytes = [Text.Encoding]::UTF8.GetBytes($value)
    $sha = [Security.Cryptography.SHA256]::Create()
    return ([BitConverter]::ToString($sha.ComputeHash($bytes)) -replace '-', '').ToLowerInvariant().Substring(0,12)
}

function Cookie-SessionValue([string]$jar) {
    if (-not (Test-Path $jar)) { return $null }
    foreach ($line in Get-Content $jar) {
        if ($line -match '^(?:#HttpOnly_)?[^\t]+\t[^\t]+\t[^\t]+\t[^\t]+\t[^\t]+\tadmin_sehatgeh_session\t([^\t]+)$') { return $Matches[1] }
    }
    return $null
}

function Invoke-Http([string]$url, [string]$jar, [string]$method = 'GET', [hashtable]$form = @{}) {
    $tag = [IO.Path]::GetRandomFileName()
    $headers = Join-Path ([IO.Path]::GetTempPath()) ($tag + '.headers')
    $body = Join-Path ([IO.Path]::GetTempPath()) ($tag + '.body')
    $args = @('--insecure','--silent','--show-error','--location','--max-time','20','--dump-header',$headers,'--output',$body,'--cookie',$jar,'--cookie-jar',$jar,'--user-agent','governance-session-stability')
    $formFile = $null
    if ($method -eq 'POST') {
        $formFile = Join-Path ([IO.Path]::GetTempPath()) ($tag + '.form')
        $encoded = (($form.GetEnumerator() | ForEach-Object { [uri]::EscapeDataString([string]$_.Key) + '=' + [uri]::EscapeDataString([string]$_.Value) }) -join '&')
        Set-Content -LiteralPath $formFile -Value $encoded -NoNewline
        $args += @('--request','POST','--data-binary',('@' + $formFile))
    }
    $args += $url
    & curl.exe @args 2>$null
    $exit = $LASTEXITCODE
    $headerText = if (Test-Path $headers) { Get-Content $headers -Raw } else { '' }
    $bodyText = if (Test-Path $body) { Get-Content $body -Raw } else { '' }
    $status = 0
    $headerLines = $headerText -split "`r?`n"; [array]::Reverse($headerLines)
    foreach ($line in $headerLines) {
        if ($line -match '^HTTP/\S+\s+(\d{3})') { $status = [int]$Matches[1]; break }
    }
    $setSession = [bool]($headerText -match '(?im)^Set-Cookie:\s*admin_sehatgeh_session=')
    Remove-Item -LiteralPath $headers,$body -Force -ErrorAction SilentlyContinue
    if ($formFile) { Remove-Item -LiteralPath $formFile -Force -ErrorAction SilentlyContinue }
    return [pscustomobject]@{ Status=$status; Headers=$headerText; Body=$bodyText; SetSession=$setSession; Exit=$exit }
}

function Session-Fields([string]$container, [string]$sessionId) {
    if ([string]::IsNullOrWhiteSpace($sessionId)) { return @{ Exists=$false; Id=$false; Level=$false; Login=$false } }
    $safe = $sessionId -replace '[^A-Za-z0-9,-]', ''
    $cmd = "f=/var/run/doclinc/sessions/admin_sehatgeh_session$safe; if [ ! -f `$f ]; then echo NO; else printf 'YES '; grep -q 'id|i:' `$f && printf 'ID ' || true; grep -q 'level|' `$f && printf 'LEVEL ' || true; grep -q 'is_login|' `$f && printf 'LOGIN' || true; fi"
    $result = docker exec $container sh -c $cmd 2>$null
    $text = ($result -join ' ').Trim()
    return @{ Exists=$text.StartsWith('YES'); Id=$text -match '\bID\b'; Level=$text -match '\bLEVEL\b'; Login=$text -match 'LOGIN' }
}

function Test-Sequence([string]$name) {
    $dir = Join-Path ([IO.Path]::GetTempPath()) ('doclinc-session-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $dir | Out-Null
    $jar = Join-Path $dir 'current.txt'; $oldJar = Join-Path $dir 'old.txt'
    try {
        $loginPage = Invoke-Http ($BaseUrl.TrimEnd('/') + '/admin_menu/') $jar
        $pre = Cookie-SessionValue $jar
        $csrf = if ($loginPage.Body -match 'name="csrf_test_name"\s+value="([^"]+)"') { $Matches[1] } else { '' }
        if (-not $pre -or -not $csrf) { throw 'login_page_or_cookie_missing' }
        Copy-Item $jar $oldJar
        $login = Invoke-Http ($BaseUrl.TrimEnd('/') + '/admin_menu/index.php?/login/ceklogin') $jar 'POST' @{csrf_test_name=$csrf;email=$AdminEmail;password=$AdminPassword}
        $post = Cookie-SessionValue $jar
        $fields = Session-Fields $PhpContainer $post
        $oldFields = Session-Fields $PhpContainer $pre
        $protected = Invoke-Http ($BaseUrl.TrimEnd('/') + '/admin_menu/index.php?/kelola_staff_puskesmas') $jar
        $oldProtected = Invoke-Http ($BaseUrl.TrimEnd('/') + '/admin_menu/index.php?/kelola_staff_puskesmas') $oldJar
        $authorized = ($protected.Headers -notmatch '(?im)^(?:Location|Refresh):.*(?:login|/admin_menu/index\.php\?/)$')
        $oldInvalid = ($oldProtected.Headers -match '(?im)^(?:Location|Refresh):')
        $result = [ordered]@{
            Sequence=$name; LoginSuccess=($login.Status -eq 200 -and $login.Body.Trim() -eq '1'); SessionRotated=($pre -ne $post); NewCookieReturned=$login.SetSession; AuthFieldsPresent=($fields.Exists -and $fields.Id -and $fields.Level -and $fields.Login); OldSessionInvalid=($oldInvalid -and -not $oldFields.Login); ProtectedAuthorized=$authorized; PreSessionHash=(Hash-Text $pre); PostSessionHash=(Hash-Text ([string]$post)); CookieCount=((Get-Content $jar | Where-Object { $_ -match 'admin_sehatgeh_session' }).Count); ProtectedStatus=$protected.Status; ProtectedRefresh=($protected.Headers -match '(?im)^Refresh:');
        }
        $result.Pass = ($result.LoginSuccess -and $result.SessionRotated -and $result.NewCookieReturned -and $result.AuthFieldsPresent -and $result.OldSessionInvalid -and $result.ProtectedAuthorized)
        [pscustomobject]$result
    } catch {
        [pscustomobject]@{ Sequence=$name; Pass=$false; Failure=$_.Exception.Message }
    } finally { Remove-Item -LiteralPath $dir -Recurse -Force -ErrorAction SilentlyContinue }
}

$all = @()
foreach ($name in @('A','B','C','D','E','F')) { $all += Test-Sequence $name }
$all | ConvertTo-Json -Compress | Set-Content -LiteralPath $OutputPath -Encoding UTF8
$all | ForEach-Object { Write-Output ("SEQUENCE_{0}={1}" -f $_.Sequence, ($(if ($_.Pass) {'PASS'} else {'FAIL'}))) }
if (($all | Where-Object { -not $_.Pass }).Count -gt 0) { exit 1 }
exit 0
