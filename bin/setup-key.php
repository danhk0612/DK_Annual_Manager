<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI에서만 실행할 수 있습니다.\n");
    exit(2);
}

$root = dirname(__DIR__);
$storage = $root . '/storage';
$keyPath = $storage . '/setup.key';

if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
    fwrite(STDERR, "storage 디렉터리를 만들 수 없습니다.\n");
    exit(1);
}

$key = bin2hex(random_bytes(24));
if (file_put_contents($keyPath, $key . PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "setup key를 저장하지 못했습니다.\n");
    exit(1);
}
@chmod($keyPath, 0600);

fwrite(STDOUT, "초기 설정 키를 새로 생성했습니다.\n");
fwrite(STDOUT, "브라우저에서 아래 경로를 사용하세요.\n\n");
fwrite(STDOUT, "/setup?setup_key=" . $key . "\n\n");
fwrite(STDOUT, "설정 완료 후 키 파일은 자동 삭제됩니다.\n");
