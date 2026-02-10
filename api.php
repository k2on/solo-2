<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$DATA_FILE = __DIR__ . '/data/entries.json';

// --- Helper Functions ---

function loadEntries() {
    global $DATA_FILE;
    if (!file_exists($DATA_FILE)) {
        $initial = generateInitialData();
        saveEntries($initial);
        return $initial;
    }
    $json = file_get_contents($DATA_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $initial = generateInitialData();
        saveEntries($initial);
        return $initial;
    }
    return $data;
}

function saveEntries($entries) {
    global $DATA_FILE;
    $dir = dirname($DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($DATA_FILE, json_encode(array_values($entries), JSON_PRETTY_PRINT));
}

function generateInitialData() {
    $BOOKS = [
        'Genesis','Exodus','Leviticus','Numbers','Deuteronomy','Joshua','Judges','Ruth',
        '1 Samuel','2 Samuel','1 Kings','2 Kings','1 Chronicles','2 Chronicles','Ezra',
        'Nehemiah','Esther','Job','Psalms','Proverbs','Ecclesiastes','Song of Solomon',
        'Isaiah','Jeremiah','Lamentations','Ezekiel','Daniel','Hosea','Joel','Amos',
        'Obadiah','Jonah','Micah','Nahum','Habakkuk','Zephaniah','Haggai','Zechariah',
        'Malachi','Matthew','Mark','Luke','John','Acts','Romans','1 Corinthians',
        '2 Corinthians','Galatians','Ephesians','Philippians','Colossians',
        '1 Thessalonians','2 Thessalonians','1 Timothy','2 Timothy','Titus','Philemon',
        'Hebrews','James','1 Peter','2 Peter','1 John','2 John','3 John','Jude','Revelation'
    ];
    $CHAPTER_MAP = [
        50,40,27,36,34,24,21,4,31,24,22,25,29,36,10,13,10,42,150,31,12,8,66,52,5,48,12,14,3,9,1,4,7,3,3,3,2,14,4,28,16,24,21,28,16,16,13,6,6,4,4,5,3,6,4,3,1,13,5,5,3,5,1,1,1,22
    ];

    $startDate = strtotime("2025-11-01");
    $entries = [];
    for ($i = 0; $i < 30; $i++) {
        $bookIndex = $i % count($BOOKS);
        $maxChapter = $CHAPTER_MAP[$bookIndex];
        $chapter = rand(1, $maxChapter);
        $entries[] = [
            'id' => $i + 1,
            'date' => ($startDate + (86400 * $i)) * 1000, // milliseconds
            'start' => [
                'book' => $bookIndex,
                'chapter' => $chapter
            ]
        ];
    }
    return $entries;
}

function validateEntry($data) {
    $errors = [];

    if (!isset($data['date']) || !is_numeric($data['date'])) {
        $errors[] = "A valid date is required.";
    }

    if (!isset($data['start']) || !is_array($data['start'])) {
        $errors[] = "Start location is required.";
    } else {
        if (!isset($data['start']['book']) || $data['start']['book'] === "" || !is_numeric($data['start']['book'])) {
            $errors[] = "A valid book selection is required.";
        } else {
            $bookIndex = intval($data['start']['book']);
            if ($bookIndex < 0 || $bookIndex > 65) {
                $errors[] = "Book index must be between 0 and 65.";
            }
        }

        if (!isset($data['start']['chapter']) || !is_numeric($data['start']['chapter'])) {
            $errors[] = "A valid chapter number is required.";
        } else {
            $chapter = intval($data['start']['chapter']);
            if ($chapter < 1) {
                $errors[] = "Chapter must be at least 1.";
            }
            // Validate chapter against book's max chapters
            $CHAPTER_MAP = [50,40,27,36,34,24,21,4,31,24,22,25,29,36,10,13,10,42,150,31,12,8,66,52,5,48,12,14,3,9,1,4,7,3,3,3,2,14,4,28,16,24,21,28,16,16,13,6,6,4,4,5,3,6,4,3,1,13,5,5,3,5,1,1,1,22];
            if (isset($data['start']['book']) && is_numeric($data['start']['book'])) {
                $bi = intval($data['start']['book']);
                if ($bi >= 0 && $bi <= 65 && $chapter > $CHAPTER_MAP[$bi]) {
                    $BOOKS = ['Genesis','Exodus','Leviticus','Numbers','Deuteronomy','Joshua','Judges','Ruth','1 Samuel','2 Samuel','1 Kings','2 Kings','1 Chronicles','2 Chronicles','Ezra','Nehemiah','Esther','Job','Psalms','Proverbs','Ecclesiastes','Song of Solomon','Isaiah','Jeremiah','Lamentations','Ezekiel','Daniel','Hosea','Joel','Amos','Obadiah','Jonah','Micah','Nahum','Habakkuk','Zephaniah','Haggai','Zechariah','Malachi','Matthew','Mark','Luke','John','Acts','Romans','1 Corinthians','2 Corinthians','Galatians','Ephesians','Philippians','Colossians','1 Thessalonians','2 Thessalonians','1 Timothy','2 Timothy','Titus','Philemon','Hebrews','James','1 Peter','2 Peter','1 John','2 John','3 John','Jude','Revelation'];
                    $errors[] = "{$BOOKS[$bi]} only has {$CHAPTER_MAP[$bi]} chapters.";
                }
            }
        }
    }

    return $errors;
}

function getNextId($entries) {
    $maxId = 0;
    foreach ($entries as $e) {
        if ($e['id'] > $maxId) $maxId = $e['id'];
    }
    return $maxId + 1;
}

// --- Routing ---

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// GET /api/entries — list all (with optional paging)
// GET /api/entries/{id} — get one
// POST /api/entries — create
// PUT /api/entries/{id} — update
// DELETE /api/entries/{id} — delete
// GET /api/stats — get statistics

// Simple router
if (preg_match('#^/api/stats$#', $uri)) {
    if ($method === 'GET') {
        $entries = loadEntries();
        $total = count($entries);

        // Chapters read calculation
        $CHAPTER_MAP = [50,40,27,36,34,24,21,4,31,24,22,25,29,36,10,13,10,42,150,31,12,8,66,52,5,48,12,14,3,9,1,4,7,3,3,3,2,14,4,28,16,24,21,28,16,16,13,6,6,4,4,5,3,6,4,3,1,13,5,5,3,5,1,1,1,22];

        // Sort by date descending
        usort($entries, function($a, $b) { return $b['date'] - $a['date']; });

        $totalChapters = 0;
        if (count($entries) > 1) {
            for ($i = 0; $i < count($entries) - 1; $i++) {
                $curr = $entries[$i];
                $next = $entries[$i + 1];
                $currAbs = 0;
                for ($j = 0; $j < intval($curr['start']['book']); $j++) {
                    $currAbs += $CHAPTER_MAP[$j];
                }
                $currAbs += intval($curr['start']['chapter']);

                $nextAbs = 0;
                for ($j = 0; $j < intval($next['start']['book']); $j++) {
                    $nextAbs += $CHAPTER_MAP[$j];
                }
                $nextAbs += intval($next['start']['chapter']);

                $totalChapters += $currAbs - $nextAbs;
            }
        }

        // Books touched
        $booksCovered = [];
        foreach ($entries as $e) {
            $booksCovered[intval($e['start']['book'])] = true;
        }
        $uniqueBooks = count($booksCovered);

        // OT vs NT split
        $otCount = 0;
        $ntCount = 0;
        foreach ($entries as $e) {
            if (intval($e['start']['book']) < 39) {
                $otCount++;
            } else {
                $ntCount++;
            }
        }

        // Streak calculation (consecutive days)
        $dates = [];
        foreach ($entries as $e) {
            $dayStr = date('Y-m-d', intval($e['date']) / 1000);
            $dates[$dayStr] = true;
        }
        ksort($dates);
        $dateKeys = array_keys($dates);
        $maxStreak = 0;
        $currentStreak = 1;
        for ($i = 1; $i < count($dateKeys); $i++) {
            $prev = strtotime($dateKeys[$i - 1]);
            $curr = strtotime($dateKeys[$i]);
            if (($curr - $prev) === 86400) {
                $currentStreak++;
            } else {
                if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;
                $currentStreak = 1;
            }
        }
        if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;

        echo json_encode([
            'totalEntries' => $total,
            'totalChapters' => $totalChapters,
            'uniqueBooks' => $uniqueBooks,
            'otEntries' => $otCount,
            'ntEntries' => $ntCount,
            'longestStreak' => $maxStreak
        ]);
        exit;
    }
} elseif (preg_match('#^/api/entries(?:/(\d+))?$#', $uri, $matches)) {
    $id = isset($matches[1]) ? intval($matches[1]) : null;

    switch ($method) {
        case 'GET':
            $entries = loadEntries();
            if ($id !== null) {
                // Get single entry
                $found = null;
                foreach ($entries as $e) {
                    if ($e['id'] == $id) { $found = $e; break; }
                }
                if ($found) {
                    echo json_encode($found);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Entry not found']);
                }
            } else {
                // List with paging
                usort($entries, function($a, $b) { return $b['date'] - $a['date']; });

                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $perPage = 10;
                $totalEntries = count($entries);
                $totalPages = max(1, ceil($totalEntries / $perPage));
                $page = min($page, $totalPages);
                $offset = ($page - 1) * $perPage;
                $paged = array_slice($entries, $offset, $perPage);

                echo json_encode([
                    'entries' => $paged,
                    'page' => $page,
                    'totalPages' => $totalPages,
                    'totalEntries' => $totalEntries
                ]);
            }
            exit;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $errors = validateEntry($input);
            if (count($errors) > 0) {
                http_response_code(400);
                echo json_encode(['errors' => $errors]);
                exit;
            }
            $entries = loadEntries();
            $newEntry = [
                'id' => getNextId($entries),
                'date' => intval($input['date']),
                'start' => [
                    'book' => intval($input['start']['book']),
                    'chapter' => intval($input['start']['chapter'])
                ]
            ];
            $entries[] = $newEntry;
            saveEntries($entries);
            http_response_code(201);
            echo json_encode($newEntry);
            exit;

        case 'PUT':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Entry ID required']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $errors = validateEntry($input);
            if (count($errors) > 0) {
                http_response_code(400);
                echo json_encode(['errors' => $errors]);
                exit;
            }
            $entries = loadEntries();
            $found = false;
            foreach ($entries as &$e) {
                if ($e['id'] == $id) {
                    $e['date'] = intval($input['date']);
                    $e['start'] = [
                        'book' => intval($input['start']['book']),
                        'chapter' => intval($input['start']['chapter'])
                    ];
                    $found = true;
                    $updated = $e;
                    break;
                }
            }
            unset($e);
            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }
            saveEntries($entries);
            echo json_encode($updated);
            exit;

        case 'DELETE':
            if ($id === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Entry ID required']);
                exit;
            }
            $entries = loadEntries();
            $found = false;
            $entries = array_filter($entries, function($e) use ($id, &$found) {
                if ($e['id'] == $id) { $found = true; return false; }
                return true;
            });
            if (!$found) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit;
            }
            saveEntries($entries);
            echo json_encode(['success' => true]);
            exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
