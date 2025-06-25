<?php
session_start();
define('MAX_COMMENTS_PER_PAGE', 10);
define('MAX_COMMENTS_PER_IP', 2);
define('BLOCK_TIME', 24 * 60 * 60);
define('MAX_COMMENT_LENGTH', 128);
define('MIN_COMMENT_LENGTH', 3);

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function isSpam($comment, $existingComments) {
    $comment = strtolower(trim($comment));
    foreach ($existingComments as $existing) {
        $existingText = strtolower(trim(preg_replace('/^\[.*?\]\s*/', '', $existing)));
        if (similar_text($comment, $existingText) > strlen($comment) * 0.8) {
            return true;
        }
    }
    
    if (preg_match('/(.)\1{4,}/', $comment)) {
        return true;
    }
    
    return false;
}

function cleanComment($comment) {
    $comment = trim($comment);
    $comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
    $comment = preg_replace('/[^\p{L}\p{N}\s\.,!?\-()]/u', '', $comment);
    return $comment;
}

$error = '';
if ($_POST['comment'] ?? false) {
    $userIP = getUserIP();
    $comment = trim($_POST['comment']);
    if (strlen($comment) < MIN_COMMENT_LENGTH) {
        $error = 'Комментарий слишком короткий (минимум ' . MIN_COMMENT_LENGTH . ' символа)';
    } elseif (strlen($comment) > MAX_COMMENT_LENGTH) {
        $error = 'Комментарий слишком длинный (максимум ' . MAX_COMMENT_LENGTH . ' символов)';
    } else {
        $allComments = [];
        if (file_exists('comments.txt')) {
            $allComments = array_filter(explode("\n", file_get_contents('comments.txt')));
        }
        
        if (isSpam($comment, $allComments)) {
            $error = 'Комментарий похож на спам или уже существует';
        } else {
            $ipData = [];
            if (file_exists('ip_data.json')) {
                $ipData = json_decode(file_get_contents('ip_data.json'), true) ?: [];
            }
            
            $currentTime = time();
            if (isset($ipData[$userIP])) {
                $ipData[$userIP] = array_filter($ipData[$userIP], function($timestamp) use ($currentTime) {
                    return ($currentTime - $timestamp) < BLOCK_TIME;
                });
                
                if (count($ipData[$userIP]) >= MAX_COMMENTS_PER_IP) {
                    $error = 'Пошел нахуй, через 24 часа сможешь снова написать';
                }
            }
            
            if (empty($error)) {
                $cleanedComment = cleanComment($comment);
                
                if (!empty($cleanedComment)) {
                    $timestamp = date('Y-m-d H:i:s');
                    $commentData = "[$timestamp] $cleanedComment\n";
                    file_put_contents('comments.txt', $commentData, FILE_APPEND | LOCK_EX);
                    
                    if (!isset($ipData[$userIP])) {
                        $ipData[$userIP] = [];
                    }
                    $ipData[$userIP][] = $currentTime;
                    file_put_contents('ip_data.json', json_encode($ipData), LOCK_EX);
                    
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        }
    }
}

// да, да, все комментарии хранятся в ебучем .txt
$comments = [];
if (file_exists('comments.txt')) {
    $comments = array_reverse(array_filter(explode("\n", file_get_contents('comments.txt'))));
}

$currentPage = max(1, intval($_GET['page'] ?? 1));
$totalComments = count($comments);
$totalPages = ceil($totalComments / MAX_COMMENTS_PER_PAGE);
$offset = ($currentPage - 1) * MAX_COMMENTS_PER_PAGE;
$commentsOnPage = array_slice($comments, $offset, MAX_COMMENTS_PER_PAGE);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
            line-height: 1.6; 
            background-color: #1a1a1a; 
            color: #e0e0e0; 
        }
        .header { 
            border-bottom: 2px solid #444; 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }
        .part { 
            background: #2d2d2d; 
            padding: 10px; 
            margin: 10px 0; 
            border-left: 4px solid #666; 
            border-radius: 5px;
        }
        .colors { 
            background: #333; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px;
        }
        .comments { 
            margin-top: 30px; 
            border-top: 2px solid #444; 
            padding-top: 20px; 
        }
        .comment { 
            background: #2a2a2a; 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 5px; 
            border-left: 3px solid #555;
        }
        .comment-form textarea { 
            width: 100%; 
            height: 80px; 
            padding: 10px; 
            background: #333; 
            color: #e0e0e0; 
            border: 1px solid #555; 
            border-radius: 5px;
            resize: vertical;
        }
        .comment-form button { 
            background: #444; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            border-radius: 5px;
            margin-top: 10px;
        }
        .comment-form button:hover { 
            background: #555; 
        }
        .pagination {
            text-align: center;
            margin: 20px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            background: #333;
            color: #e0e0e0;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a:hover {
            background: #555;
        }
        .pagination .current {
            background: #666;
            font-weight: bold;
        }
        .error {
            background: #4a1a1a;
            color: #ff6b6b;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ff6b6b;
        }
        .char-counter {
            text-align: right;
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        a { color: #66b3ff; }
        h1, h2, h3 { color: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>P0DN3B3CbE</h1>
        <p><strong>Song:</strong> <a href="https://sp1ash.icu/1337.mp3" target="_blank">прослушать + скачать</a></p>
        <p><strong>Host:</strong> linwy, gertix</p>
        <p><strong>Theme:</strong> ???</p>
        <p><strong>Difficulty:</strong> Insane Demon - Low extreme</p>
        <p><strong>Deadline for 1 progress:</strong> 3 days</p>
    </div>

    <h2>Parts</h2>
    
    <div class="part">
        <h3>Part 01</h3>
        <p><strong>Offsets:</strong> 0–15 (15 sec)</p>
        <p><strong>Groups:</strong> 4–100</p>
    </div>

    <div class="part">
        <h3>Part 02</h3>
        <p><strong>Offsets:</strong> 15–31 (16 sec)</p>
        <p><strong>Groups:</strong> 101–200</p>
    </div>

    <div class="part">
        <h3>Part 03</h3>
        <p><strong>Offsets:</strong> 31–47 (16 sec)</p>
        <p><strong>Groups:</strong> 201–300</p>
    </div>

    <div class="part">
        <h3>Part 04</h3>
        <p><strong>Offsets:</strong> 47–62 (15 sec)</p>
        <p><strong>Groups:</strong> 301–400</p>
    </div>

    <div class="part">
        <h3>Part 05</h3>
        <p><strong>Offsets:</strong> 63–79 (16 sec)</p>
        <p><strong>Groups:</strong> 401–500</p>
    </div>

    <div class="part">
        <h3>Part 06</h3>
        <p><strong>Offsets:</strong> 79–95 (16 sec)</p>
        <p><strong>Groups:</strong> 501–600</p>
    </div>

    <div class="colors">
        <h2>Общие цвета и группы</h2>
        <ul>
            <li><strong>1 group</strong> - Alpha (FadeTime + opacity = 0)</li>
            <li><strong>2 group</strong> - follow player Y</li>
            <li><strong>1 color</strong> - white</li>
            <li><strong>2 color</strong> - black</li>
            <li><strong>Main colors:</strong> ???</li>
        </ul>
    </div>

    <div class="comments">
        <h2>Комментарии</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
            <script>alert('<?= addslashes($error) ?>');</script>
        <?php endif; ?>
        
        <form method="POST" class="comment-form">
            <textarea name="comment" placeholder="Ну тут э чо хочеш то и пиши" required maxlength="<?= MAX_COMMENT_LENGTH ?>" oninput="updateCharCounter(this)"></textarea>
            <div class="char-counter">
                <span id="charCount">0</span>/<?= MAX_COMMENT_LENGTH ?>
            </div>
            <button type="submit">Отправить</button>
        </form>

        <?php if (!empty($commentsOnPage)): ?>
            <h3>Комментарии (<?= $totalComments ?>):</h3>
            <?php foreach ($commentsOnPage as $comment): ?>
                <div class="comment"><?= $comment ?></div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?>">&laquo; Назад</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>">Вперед &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p><em>Комментариев пока нет</em></p>
        <?php endif; ?>
    </div>

    <script>
        function updateCharCounter(textarea) {
            const charCount = textarea.value.length;
            const counter = document.getElementById('charCount');
            counter.textContent = charCount;
            
            if (charCount > <?= MAX_COMMENT_LENGTH ?> * 0.9) {
                counter.style.color = '#ff6b6b';
            } else if (charCount > <?= MAX_COMMENT_LENGTH ?> * 0.7) {
                counter.style.color = '#ffa500';
            } else {
                counter.style.color = '#888';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('textarea[name="comment"]');
            if (textarea) {
                updateCharCounter(textarea);
            }
        });
    </script>
</body>
</html>