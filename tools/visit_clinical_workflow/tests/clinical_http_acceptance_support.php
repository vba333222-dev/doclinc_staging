<?php
/** Test-only real CI3 actor/lifecycle acceptance support for Task 11. */

function task11_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function task11_db()
{
    static $db;
    if ($db instanceof mysqli) {
        return $db;
    }
    $host = getenv('VCW_HTTP_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('VCW_HTTP_DB_PORT') ?: 33317);
    $name = getenv('VCW_HTTP_DB_NAME') ?: 'doclinc_visit_test';
    $user = getenv('VCW_HTTP_DB_USER') ?: 'doclinc_test';
    $pass = getenv('VCW_HTTP_DB_PASSWORD') ?: 'doclinc_test_only';
    task11_assert($name === 'doclinc_visit_test', 'disposable_database_required');
    task11_assert(in_array(strtolower($host), array('127.0.0.1', 'localhost', 'task11-db'), true), 'disposable_database_host_required');
    task11_assert(in_array($port, array(3306, 33317), true), 'disposable_database_port_required');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
    task11_assert(strpos((string) $db->query('SELECT VERSION()')->fetch_row()[0], '10.11.') === 0, 'mariadb_10_11_required');
    return $db;
}

function task11_sql($sql, array $params = array())
{
    $db = task11_db();
    if (!$params) {
        return $db->query($sql);
    }
    $statement = $db->prepare($sql);
    $types = '';
    foreach ($params as $value) {
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }
    $refs = array($types);
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array(array($statement, 'bind_param'), $refs);
    $statement->execute();
    return $statement;
}

/**
 * Remove only rows owned by one browser run. The run id is supplied by the
 * test process and is embedded in disposable usernames/facility names. This
 * intentionally never performs a schema-wide DELETE and fails closed unless
 * the dedicated test database is selected.
 */
function task11_cleanup_run($runId)
{
    task11_assert(is_string($runId) && preg_match('/^[a-z0-9_-]{8,48}$/i', $runId), 'invalid_task11_run_id');
    $db = task11_db();
    $name = (string) $db->query('SELECT DATABASE()')->fetch_row()[0];
    task11_assert($name === 'doclinc_visit_test', 'disposable_database_required');
    $like = 't11_' . $runId . '_%';
    $statement = $db->prepare('SELECT userId FROM users WHERE username LIKE ?');
    $statement->bind_param('s', $like);
    $statement->execute();
    $result = $statement->get_result();
    $userIds = array();
    while ($row = $result->fetch_row()) {
        $userIds[] = (int) $row[0];
    }
    $statement->close();
    $facilityLike = 'Task 11 disposable facility ' . $runId . '%';
    $statement = $db->prepare('SELECT kode_pkm FROM m_puskesmas WHERE nama_puskesmas LIKE ?');
    $statement->bind_param('s', $facilityLike);
    $statement->execute();
    $result = $statement->get_result();
    $facilities = array();
    while ($row = $result->fetch_row()) {
        $facilities[] = (string) $row[0];
    }
    $statement->close();
    if (!$userIds && !$facilities) {
        return;
    }
    $requestIds = array();
    if ($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $query = $db->prepare('SELECT request_id FROM requests WHERE user_id IN (' . $placeholders . ')');
        $types = str_repeat('i', count($userIds));
        $params = array($types);
        foreach ($userIds as $key => $value) {
            $params[] = &$userIds[$key];
        }
        call_user_func_array(array($query, 'bind_param'), $params);
        $query->execute();
        $rows = $query->get_result();
        while ($row = $rows->fetch_row()) {
            $requestIds[] = (int) $row[0];
        }
        $query->close();
    }
    $foreignKeysDisabled = false;
    try {
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $foreignKeysDisabled = true;
        $tables = array();
        $result = $db->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION');
        while ($row = $result->fetch_assoc()) {
            $table = (string) $row['TABLE_NAME'];
            $column = (string) $row['COLUMN_NAME'];
            if ($table === 'clinical_schema_migrations') {
                continue;
            }
            $kind = null;
            if (in_array(strtolower($column), array('request_id', 'requestid'), true)) {
                $kind = 'request';
            } elseif (preg_match('/(?:^|_)(?:user|staff)_?id$/i', $column) || strtolower($column) === 'userid') {
                $kind = 'user';
            } elseif (in_array(strtolower($column), array('kode_pkm', 'facility_code', 'assigned_puskesmas_code'), true)) {
                $kind = 'facility';
            }
            if ($kind !== null) {
                $tables[$table][] = array($column, $kind);
            }
        }
        foreach ($tables as $table => $columns) {
            $clauses = array();
            $values = array();
            $types = '';
            foreach ($columns as $entry) {
                list($column, $kind) = $entry;
                $set = $kind === 'request' ? $requestIds : ($kind === 'user' ? $userIds : $facilities);
                if (!$set) {
                    continue;
                }
                $clauses[] = '`' . str_replace('`', '``', $column) . '` IN (' . implode(',', array_fill(0, count($set), '?')) . ')';
                foreach ($set as $value) {
                    $values[] = $value;
                    $types .= is_int($value) ? 'i' : 's';
                }
            }
            if (!$clauses) {
                continue;
            }
            $query = $db->prepare('DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE ' . implode(' OR ', $clauses));
            $params = array($types);
            foreach ($values as $key => $value) {
                $params[] = &$values[$key];
            }
            call_user_func_array(array($query, 'bind_param'), $params);
            $query->execute();
            $query->close();
        }
    } finally {
        if ($foreignKeysDisabled) {
            $db->query('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}

function task11_scalar($sql, array $params = array())
{
    $result = task11_sql($sql, $params);
    $row = $result instanceof mysqli_stmt ? $result->get_result()->fetch_row() : $result->fetch_row();
    return $row ? $row[0] : null;
}

function task11_cookie_header(array $cookies)
{
    $pairs = array();
    foreach ($cookies as $cookie) {
        $pair = preg_replace('/;.*$/', '', trim($cookie));
        $name = strpos($pair, '=');
        if ($name !== false) {
            $pairs[substr($pair, 0, $name)] = $pair;
        }
    }
    return implode('; ', array_values($pairs));
}

function task11_csrf_from_html($html)
{
    preg_match('/meta name="doclinc-csrf-name" content="([^"]+)"/', $html, $name);
    preg_match('/meta name="doclinc-csrf-token" content="([^"]+)"/', $html, $token);
    return array($name[1] ?? '', $token[1] ?? '');
}

function task11_json($body)
{
    $json = json_decode((string) $body, true);
    task11_assert(is_array($json), 'invalid_json_response_body=' . substr(trim((string) $body), 0, 240));
    return $json;
}

function task11_safe_response(array $response)
{
    $body = (string) ($response['body'] ?? '');
    foreach (array('SQLSTATE', 'SELECT ', 'UPDATE ', 'INSERT ', 'password', 'stack trace', 'PHP Warning', 'PHP Notice', 'PHP Fatal', 'C:\\', '/var/www/') as $needle) {
        task11_assert(stripos($body, $needle) === false, 'unsafe_error_leak:' . $needle);
    }
    return true;
}

final class Task11HttpClient
{
    public $base;
    public $cookies = array();
    public $csrfName = '';
    public $csrfToken = '';

    public function __construct($base)
    {
        $parts = parse_url($base);
        task11_assert(is_array($parts) && ($parts['scheme'] ?? '') === 'http', 'http_base_required');
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 0);
        $allowedContainerHost = getenv('VCW_HTTP_LOCAL_CONTAINER_HOST') ?: '';
        task11_assert(($host === '127.0.0.1' || $host === 'localhost' || ($allowedContainerHost !== '' && $host === strtolower($allowedContainerHost))) && $port >= 1 && $port <= 65535, 'local_http_target_required');
        $this->base = rtrim($base, '/');
    }

    public function request($method, $path, array $fields = array(), $withCsrf = true)
    {
        $headers = array('Accept: application/json,text/html', 'Connection: close');
        $cookie = task11_cookie_header($this->cookies);
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }
        if ($withCsrf && $this->csrfName !== '' && $this->csrfToken !== '' && strtoupper($method) === 'POST') {
            $fields[$this->csrfName] = $this->csrfToken;
            $headers[] = 'X-CSRF-TOKEN: ' . $this->csrfToken;
        }
        $body = $fields ? http_build_query($fields) : '';
        if ($body !== '') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(array('http' => array(
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        )));
        $payload = @file_get_contents($this->base . '/' . ltrim($path, '/'), false, $context);
        $status = 0;
        $setCookies = array();
        foreach ($http_response_header ?? array() as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $match)) {
                $status = (int) $match[1];
            }
            if (stripos($line, 'Set-Cookie:') === 0) {
                $setCookies[] = trim(substr($line, 11));
            }
        }
        foreach ($setCookies as $cookieLine) {
            $pair = preg_replace('/;.*$/', '', $cookieLine);
            $name = strpos($pair, '=');
            if ($name !== false) {
                $this->cookies[substr($pair, 0, $name)] = $cookieLine;
            }
        }
        return array('status' => $status, 'body' => is_string($payload) ? $payload : '', 'cookies' => $setCookies);
    }

    public function bootstrapLogin()
    {
        $page = $this->request('GET', '/login', array(), false);
        task11_assert($page['status'] === 200, 'login_status_' . $page['status']);
        list($this->csrfName, $this->csrfToken) = task11_csrf_from_html($page['body']);
        task11_assert($this->csrfName !== '' && preg_match('/^[0-9a-f]{32}$/i', $this->csrfToken), 'login_csrf_missing');
        task11_assert(task11_cookie_header($this->cookies) !== '', 'login_session_cookie_missing');
        return $page;
    }

    public function login($username, $password)
    {
        $this->bootstrapLogin();
        $response = $this->request('POST', '/login/auth', array('username' => $username, 'password' => $password));
        task11_assert($response['status'] === 200 && trim($response['body']) === '1', 'login_failed_' . $username . '_' . $response['status']);
        return $response;
    }
}

final class Task11FixtureFactory
{
    public $suffix;
    public $runId;
    public $facility;
    public $unrelatedFacility;
    public $actors = array();
    private $password;

    public function __construct($restorePath = null)
    {
        if ($restorePath !== null && is_file($restorePath)) {
            $saved = json_decode((string) file_get_contents($restorePath), true);
            task11_assert(is_array($saved) && !empty($saved['actors']) && !empty($saved['request_id']), 'invalid_flag_off_state');
            $this->suffix = (string) $saved['suffix'];
            $this->facility = (string) $saved['facility'];
            $this->password = (string) $saved['password'];
            $this->actors = $saved['actors'];
            return;
        }
        $this->runId = getenv('TASK11_RUN_ID') ?: 'manual_' . bin2hex(random_bytes(4));
        task11_assert(preg_match('/^[a-z0-9_-]{8,48}$/i', $this->runId), 'invalid_task11_run_id');
        $this->suffix = 't11_' . $this->runId . '_' . bin2hex(random_bytes(5));
        $this->facility = 'T11-' . strtoupper(substr($this->suffix, -8));
        $this->unrelatedFacility = 'T11-X' . strtoupper(substr($this->suffix, -7));
        $this->password = 'Task11-' . bin2hex(random_bytes(8));
        task11_sql('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($this->facility, 'Task 11 disposable facility ' . $this->runId, 'aktif'));
        task11_sql('INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,?)', array($this->unrelatedFacility, 'Task 11 unrelated facility ' . $this->runId, 'aktif'));
        $this->createActors();
    }

    public function password()
    {
        return $this->password;
    }

    private function nextId()
    {
        do {
            $id = random_int(910000000, 918999999);
            $u = (int) task11_scalar('SELECT COUNT(*) FROM users WHERE userId=?', array($id));
            $r = (int) task11_scalar('SELECT COUNT(*) FROM requests WHERE request_id=?', array($id));
        } while ($u || $r);
        return $id;
    }

    private function user($id, $name, $role, $remark, $status = 'aktif')
    {
        $username = $this->suffix . '_' . strtolower($role) . '_' . $id;
        $hash = password_hash($this->password, PASSWORD_BCRYPT, array('cost' => 4));
        task11_sql('INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,password_changed_at,remark) VALUES (?,?,?,?,?,?,?,?,?,?)', array($id, $name, $username . '@invalid.test', $username, $hash, $role, $status, 0, date('Y-m-d H:i:s'), $remark));
        return array('id' => $id, 'username' => $username, 'password' => $this->password, 'role' => $role, 'status' => $status, 'name' => $name);
    }

    private function createActors()
    {
        // Keep the synthetic command-center user lowest within this unique
        // facility so the canonical identity helper selects it deterministically.
        do {
            $command = random_int(910000000, 910900000);
            $occupied = (int) task11_scalar('SELECT COUNT(*) FROM users WHERE userId BETWEEN ? AND ?', array($command, $command + 5));
            $occupied += (int) task11_scalar('SELECT COUNT(*) FROM puskesmas_staff WHERE staff_id BETWEEN ? AND ?', array($command, $command + 5));
        } while ($occupied > 0);
        $warga = $command + 1;
        $rd = $command + 2;
        $doctor = $command + 3;
        $nondoctor = $command + 4;
        $unrelated = $command + 5;
        $this->actors['COMMAND_CENTER'] = $this->user($command, 'Task11 Command Center', 'dokter', $this->facility);
        $this->actors['WARGA'] = $this->user($warga, 'Task11 Warga', 'warga', $this->facility);
        $this->actors['RD'] = $this->user($rd, 'Task11 Responsible Doctor', 'dokter', $this->facility);
        $this->actors['DOCTOR_PERFORMER'] = $this->user($doctor, 'Task11 Doctor Performer', 'dokter', $this->facility);
        $this->actors['NONDOCTOR_PERFORMER'] = $this->user($nondoctor, 'Task11 Nurse Performer', 'dokter', $this->facility);
        $this->actors['UNRELATED_DOCTOR'] = $this->user($unrelated, 'Task11 Unrelated Doctor', 'dokter', 'T11-UNRELATED');
        $this->staff($rd, 'Task11 RD', 'dokter');
        $this->staff($doctor, 'Task11 Doctor Performer', 'dokter');
        $this->staff($nondoctor, 'Task11 Nurse Performer', 'Perawat');
        $this->staff($unrelated, 'Task11 Unrelated Doctor', 'dokter', $this->unrelatedFacility);
    }

    private function staff($id, $name, $profession, $facility = null)
    {
        $facility = $facility ?: $this->facility;
        task11_sql('INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', array($id, $facility, $id, $name, $profession, 'aktif'));
        if ((int) task11_scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name="nakes_facility_placements"')) {
            task11_sql('INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', array($id, $facility, '2026-01-01', 'active', $id));
        }
    }

    public function actor($name)
    {
        task11_assert(isset($this->actors[$name]), 'unknown_actor_' . $name);
        return $this->actors[$name];
    }

    public function specialActor($name, $role, $status, $remark = '')
    {
        return $this->user($this->nextId(), $name, $role, $remark, $status);
    }

    public function request($mode = 'visit', $status = 'Accepted', $withResponsible = true)
    {
        $id = $this->nextId();
        $warga = $this->actor('WARGA')['id'];
        $rd = $this->actor('RD')['id'];
        task11_sql('INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array($id, $warga, 'Task11 synthetic ' . $mode, $status, date('Y-m-d H:i:s'), 'Task11 disposable', $this->facility, 'Task 11 disposable facility', $mode === 'visit' ? 'not_started' : null, $mode === 'visit' ? 'visit' : 'non_visit', $withResponsible ? $rd : null));
        if ($withResponsible) {
            task11_sql('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($id, $rd, $rd, $rd, 'aktif'));
        }
        // A clinical record is an existing prerequisite produced by the
        // consultation surface; it is fixture setup, not a lifecycle shortcut.
        if (task11_scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name="medicalrecords"')) {
            task11_sql('INSERT INTO medicalrecords (request_id,diagnosis,treatment,recommendations,anamnesis,responsible_doctor_user_id,recorded_by_user_id) VALUES (?,?,?,?,?,?,?)', array($id, 'Task11 initial diagnosis', 'Task11 initial treatment', 'Task11 initial recommendations', 'Task11 initial anamnesis', $rd, $rd));
        }
        return $id;
    }

    public function legacyRequest($status = 'Pending')
    {
        $id = $this->nextId();
        $warga = $this->actor('WARGA')['id'];
        task11_sql('INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status,consultation_mode,responsible_doctor_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array($id, $warga, 'Task11 synthetic legacy', $status, date('Y-m-d H:i:s'), 'Task11 disposable', $this->facility, 'Task 11 disposable facility', null, null, null));
        return $id;
    }

    public function historicalCompletedRequest()
    {
        return $this->request('visit', 'Completed', false);
    }

    public function saveState($path, $requestId)
    {
        file_put_contents($path, json_encode(array('suffix' => $this->suffix, 'facility' => $this->facility, 'password' => $this->password, 'actors' => $this->actors, 'request_id' => (int) $requestId), JSON_UNESCAPED_SLASHES));
    }

    public function stateRequestId($path)
    {
        $saved = json_decode((string) file_get_contents($path), true);
        return (int) ($saved['request_id'] ?? 0);
    }
}

function task11_surface(Task11HttpClient $client, $path)
{
    $response = $client->request('GET', $path, array(), false);
    task11_safe_response($response);
    task11_assert($response['status'] === 200, 'surface_status_' . $path . '_' . $response['status']);
    return task11_json($response['body']);
}

function task11_post(Task11HttpClient $client, $path, array $fields)
{
    $response = $client->request('POST', $path, $fields, true);
    task11_safe_response($response);
    return array($response, task11_json($response['body']));
}
