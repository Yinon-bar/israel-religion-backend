<?php
// ===== הגדרות DB (עדכן לפי XAMPP שלך) =====
$dbHost = 'srv1048.hstgr.io';
$dbName = 'u528206822_israelreligion';
$dbUser = 'u528206822_inonbar';
$dbPass = 'INONbar@0505713460'; // ב-XAMPP לרוב ריק למשתמש root

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
  echo json_encode(['ok' => false, 'error' => 'db_fail']);
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
    echo json_encode(['ok' => false, 'error' => 'results_fail']);
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
      echo json_encode(['ok' => false, 'error' => 'bad_json']);
      exit;
    }

    // echo json_encode(['ok' => false, 'error' => $data]);

    $userId = trim((string)($data['user_id'] ?? ''));
    $choice = trim((string)($data['choice'] ?? ''));

    if ($userId === '' || $choice === "") {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'bad_params']);
      exit;
    }

    $stmt = $pdo->prepare("INSERT INTO seker (userID, isReligion) VALUES (?, ?)");
    $stmt->execute([$userId, $choice]);

    echo json_encode(['ok' => true]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'vote_fail']);
  }
  exit;
}

// אם לא נתיב מוכר
http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'not_found']);
