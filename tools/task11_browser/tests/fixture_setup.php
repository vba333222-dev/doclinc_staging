<?php
putenv('VCW_HTTP_DB_HOST=' . (getenv('VCW_HTTP_DB_HOST') ?: (getenv('DB_HOST') ?: 'task11-db')));
putenv('VCW_HTTP_DB_PORT=' . (getenv('VCW_HTTP_DB_PORT') ?: (getenv('DB_PORT') ?: '3306')));
putenv('VCW_HTTP_DB_NAME=' . (getenv('VCW_HTTP_DB_NAME') ?: (getenv('DB_NAME') ?: 'doclinc_visit_test')));
putenv('VCW_HTTP_DB_USER=' . (getenv('VCW_HTTP_DB_USER') ?: (getenv('DB_USER') ?: 'doclinc_test')));
putenv('VCW_HTTP_DB_PASSWORD=' . (getenv('VCW_HTTP_DB_PASSWORD') ?: (getenv('DB_PASS') ?: 'doclinc_test_only')));
require_once dirname(__DIR__, 2) . '/visit_clinical_workflow/tests/clinical_http_acceptance_support.php';
$runId = getenv('TASK11_RUN_ID') ?: '';
task11_assert($runId !== '', 'task11_run_id_required');
task11_cleanup_run($runId);
$factory = new Task11FixtureFactory();
$voicePhotoFixture = getenv('TASK11_VOICE_PHOTO') === '1';
$voiceTextOnlyFixture = getenv('TASK11_VOICE_TEXT_ONLY') === '1';
$voiceLongNameFixture = getenv('TASK11_VOICE_LONG_NAME') === '1';
$voiceInvalidPhotoFixture = getenv('TASK11_VOICE_INVALID_PHOTO') === '1';
$chatReferenceFixture = getenv('TASK11_CHAT_REFERENCE') === '1';
if ($voicePhotoFixture || $voiceTextOnlyFixture || $voiceLongNameFixture || $voiceInvalidPhotoFixture) {
    // Test-only disposable profile data uses the same private storage key and
    // users.foto contract as production. It is removed with the disposable
    // container and never becomes application source or seeded data.
    $provider = $factory->actor('RD');
    $photoKey = $voiceInvalidPhotoFixture
        ? 'profile-images/' . substr(hash('sha256', $runId . '-missing-voice-photo'), 0, 32) . '.png'
        : 'profile-images/' . substr(hash('sha256', $runId . '-voice-photo'), 0, 32) . '.png';
    $providerName = $voiceLongNameFixture ? 'dr. Muhammad Rizky Pratama' : 'dr. Bima Verifikasi';
    task11_sql('UPDATE users SET nama=?, foto=? WHERE userId=?', array($providerName, $photoKey, $provider['id']));
    task11_sql('UPDATE puskesmas_staff SET nama=?, profesi=? WHERE user_id=?', array($providerName, 'Dokter', $provider['id']));
    if ($voiceTextOnlyFixture || $voiceLongNameFixture) {
        task11_sql('UPDATE users SET foto=NULL WHERE userId=?', array($provider['id']));
    }
    // The disposable compose image uses the production default private root
    // (`dirname(dirname(FCPATH))/private/profile-images`).
    $photoRoot = '/private/profile-images';
    if (!is_dir($photoRoot)) {
        @mkdir($photoRoot, 0700, true);
    }
    if (!$voiceInvalidPhotoFixture) {
        // Deterministic synthetic mint image: valid profile-media bytes, no
        // human/stock content, and visually distinct from the no-photo path.
        file_put_contents($photoRoot . DIRECTORY_SEPARATOR . basename($photoKey), base64_decode('iVBORw0KGgoAAAANSUhEUklIRFIAAABAAAAAQAgGAAAAqmlx3gAAAJhJREFUSURBVHic7dAxEQAgEMCw979iCguoARkZ6JC911ln35+NDtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QGuADtAaoAO0BugArQE6QHsHUzL+TFmKpAAAAABJRU5ErkJggg=='));
    }
    if ($voicePhotoFixture && !$voiceInvalidPhotoFixture) {
        file_put_contents($photoRoot . DIRECTORY_SEPARATOR . basename($photoKey), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAmElEQVR4nO3QMREAIBDAsPe/YgoLqAEZGeiQvddZZ9+fjQ7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkBrgA7QGqADtAboAK0BOkB7B1My/kxZiqQAAAAASUVORK5CYII='));
    }
    if ($voicePhotoFixture && !$voiceInvalidPhotoFixture) {
        // Ensure the disposable photo is a fully decodable local PNG so the
        // production profile endpoint can prove the photo-present branch.
        file_put_contents($photoRoot . DIRECTORY_SEPARATOR . basename($photoKey), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAACQSURBVHhe7dAxAQAgEIBAo5j/Rwu+BWgAwy2MnPtmzRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0aQBFE0aQNGkARRNGkDRpAEUTRpA0UQ+YPYDT9SySp2S+YYAAAAASUVORK5CYII='));
    }
}
if ($chatReferenceFixture) {
    $warga = $factory->actor('WARGA');
    $photoKey = 'profile-images/' . substr(hash('sha256', $runId . '-chat-reference-photo'), 0, 32) . '.png';
    task11_sql('UPDATE users SET nama=?, foto=? WHERE userId=?', array('dr. Rani Permata, Sp.PD', $photoKey, $warga['id']));
    // The application resolves private profile media beneath FCPATH's parent
    // (`/private/profile-images` in the disposable web container). Keep the
    // strict Chat photo fixture on that same production storage root so the
    // normal profile-photo resolver renders it.
    $photoRoot = '/private/profile-images';
    if (!is_dir($photoRoot)) {
        @mkdir($photoRoot, 0700, true);
    }
    $fixturePhotoCandidates = array(
        '/var/www/tools/task11_browser/tests/fixtures/chat-reference-provider.png',
        '/var/www/html/tools/task11_browser/tests/fixtures/chat-reference-provider.png',
    );
    $fixturePhoto = '';
    foreach ($fixturePhotoCandidates as $candidate) {
        if (is_file($candidate)) {
            $fixturePhoto = $candidate;
            break;
        }
    }
    if (is_file($fixturePhoto)) {
        copy($fixturePhoto, $photoRoot . DIRECTORY_SEPARATOR . basename($photoKey));
    } else {
        file_put_contents($photoRoot . DIRECTORY_SEPARATOR . basename($photoKey), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAABn0lEQVR4nO1YwW3DMAykiQyVR4EuwCWyQV7NpwUEJJ/2lQ2yRBcIkEfG6AB9dYI+BBhGEisSRVpmmHsZlk0d786yre7n7xcsA8E4EIwDwTgQjAPBOBCMA8E4Fkp1w/7rxsn1m/hEneyL7CZv1U46qQYyqYu3ga3Ys+8SdkCERKiwApuzh7o65pdRbC5/ZTWcA/uami4jFBTkZ1c278BCttzh4zMerLab0lEeUNDlnt/Fcc4oO0XmI4RgHChYa5js65SnR+fyEKeZCfLu8YzQAOfT8Xw6gtBlasuown9tTf1nhAZYvrzGhMA44mi8UgSo4fJYD3ejH8rzKRyhXtprrv0ZQfmh5qc+8dGSUDrBPrCWh6pdifSH10UbaeEDd3HT2loUj4rOtorQOyFU1JHZWmT/ZIZqCYS2Flk8RAz0ujtNuzUo4Pt9X3qLy28h0pEfWJX9OUBq8vPq+3PAdgOknB/GLJ4coEnkL53LjQM0ofxFM7pxwHYDNHl+8ud14AA1kj9zdgcO2G6AmuYnh8NDO0AzkP8uk8d1gGYjf8QYn39JT6DNcgrp+wAAAABJRU5ErkJggg=='));
    }
}
$doctorRequest = $factory->request('visit');
// Initial prerequisite setup: make the doctor-performer the responsible
// clinician for its dedicated self-review scenario.
task11_sql('UPDATE requests SET responsible_doctor_user_id=? WHERE request_id=?', array($factory->actor('DOCTOR_PERFORMER')['id'], $doctorRequest));
// The canonical schema uses `diganti` for a superseded responsible-doctor
// assignment. Keep this setup within the frozen enum rather than inventing a
// test-only status value.
task11_sql('UPDATE request_responsible_doctor_assignments SET status="diganti" WHERE request_id=?', array($doctorRequest));
task11_sql('INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,NOW(6))', array($doctorRequest, $factory->actor('DOCTOR_PERFORMER')['id'], $factory->actor('DOCTOR_PERFORMER')['id'], $factory->actor('DOCTOR_PERFORMER')['id'], 'aktif'));
$requests = array(
    'visit_happy' => $factory->request('visit'), 'visit_correction' => $factory->request('visit'),
    'doctor_performer' => $doctorRequest, 'nondoctor_performer' => $factory->request('visit'),
    'nonvisit' => $factory->request('non_visit'), 'authority' => $factory->request('visit'),
    'stale' => $factory->request('visit'), 'idempotency' => $factory->request('visit'), 'post_closure' => $factory->request('visit'),
    'flag_off' => $factory->request('visit'), 'legacy' => $factory->legacyRequest(),
    'historical_completed' => $factory->historicalCompletedRequest(),
);
if ($voicePhotoFixture || $voiceTextOnlyFixture || $voiceLongNameFixture || $voiceInvalidPhotoFixture) {
    task11_sql('UPDATE requests SET assigned_nakes_user_id=?, assigned_nakes_at=NOW(), assigned_nakes_by_user_id=? WHERE request_id=?', array($provider['id'], $provider['id'], $requests['visit_happy']));
}
$special = array(
    'INACTIVE' => $factory->specialActor('Task11 Inactive Actor', 'dokter', 'nonaktif', $factory->facility),
    'UNCLASSIFIED' => $factory->specialActor('Task11 Unclassified Actor', '', 'aktif', $factory->facility),
);
echo json_encode(array('suffix' => $factory->suffix, 'facility' => $factory->facility, 'password' => $factory->password(), 'actors' => $factory->actors, 'special' => $special, 'requests' => $requests), JSON_UNESCAPED_SLASHES) . PHP_EOL;
