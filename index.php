<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// ===== כותרות JSON + CORS (פשוטות) =====
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ===== חיבור PDO =====
try {
  $pdo = new PDO(
    "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'בעיה בהתחברות למסד הנתונים']);
  exit;
}

// ===== ראוט הכי פשוט: פרמטר r =====
$r = $_GET['r'] ?? '';

// GET ?r=results  -> החזרת ספירות
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $r === 'results') {
  try {
    $sql = "
      SELECT
      SUM(isReligion = 1) AS religion,  -- חוזרים בתשובה
      SUM(isReligion = 0) AS notReligion,    -- חוזרים בשאלה
      COUNT(*) AS total
      FROM seker;
";
    $row = $pdo->query($sql)->fetch() ?: ['religion' => 0, 'notReligion' => 0, 'total' => 0];

    echo json_encode([[
      'ok'     => true,
      'isReligion' => (int)$row['religion'],
      'notReligion' => (int)$row['notReligion'],
      'total' => (int)$row['total'],
    ]]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'שליפת הנתונים נכשלה']);
  }
  exit;
}

// POST ?r=vote  body: { "user_id": "...", "choice": "sheela" | "teshuva" }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $r === 'vote') {
  try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'מבנה גייסון לא תקין']);
      exit;
    }

    $userId = trim((string)($data['user_id'] ?? ''));
    $choice = trim((string)($data['choice'] ?? ''));
    $userIdHash = hmac_id($userId);

    // echo json_encode(['message' => $userIdHash]);

    if ($userId === '' || $choice === "") {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'פרמטרים לא תקינים']);
      exit;
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO seker (userID, isReligion) VALUES (?, ?)");
    $stmt->execute([$userIdHash, $choice]);
    $inserted = ($stmt->rowCount() === 1);
    if (!$inserted) {
      http_response_code(409); // אופציונלי – 'Conflict'
      echo json_encode(['ok' => false, 'error' => 'משתמש בעל ת.ז זו כבר הצביע']);
      exit;
    }
    echo json_encode(['ok' => true, 'status' => 'הנתונים נקלטו בהצלחה, תודה על השתתפותך']);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'הצבעה נכשלה']);
  }
  exit;
}

// אם לא נתיב מוכר
http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'נתיב לא נמצא']);
