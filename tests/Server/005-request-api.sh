#!/usr/bin/env bash

cd "$( dirname "${BASH_SOURCE[0]}" )/../Fixtures/Symfony/app";

HOST=localhost
PORT=9999
API_PORT=9998
CURL_REQUEST=http://${HOST}:${API_PORT}/healthz
EXIT_CODE=0

EXPECTED_RESULT='{"ok":true}'

./console swoole:server:start --port=${PORT} --ansi --api --api-port=${API_PORT}

echo "[Info] Executing curl request: $CURL_REQUEST";
RESULT=$(curl ${CURL_REQUEST} -s)
echo "[Info] Result: $RESULT";
if [[ "$RESULT" = "$EXPECTED_RESULT" ]]; then
    echo "[Test] OK";
else
    echo "[Test] FAIL";
    EXIT_CODE=1;
fi

./console swoole:server:stop --ansi

exit ${EXIT_CODE};
