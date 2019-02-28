#!/usr/bin/env bash

cd "$( dirname "${BASH_SOURCE[0]}" )/../Fixtures/Symfony/app";

HOST=localhost
PORT=9999
API_PORT=9998
CURL_REQUEST="http://${HOST}:${API_PORT}/api/server"
EXIT_CODE=0

EXPECTED_STATUS_CODE="204"

./console swoole:server:start --port=${PORT} --ansi --api --api-port=${API_PORT}

echo "[Info] Executing cURL Request: $CURL_REQUEST";
RESULT="$(curl -X DELETE ${CURL_REQUEST} -i -s | head -n 1 | awk '{print $2}')"
echo "[Info] Result: $RESULT";
if [[ "$RESULT" = "$EXPECTED_STATUS_CODE" ]]; then
    echo "[Test] OK";
else
    echo "[Test] FAIL";
    EXIT_CODE=1;
fi

exit ${EXIT_CODE};
