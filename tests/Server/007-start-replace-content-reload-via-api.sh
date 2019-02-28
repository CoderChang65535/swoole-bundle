#!/usr/bin/env bash

cd "$( dirname "${BASH_SOURCE[0]}" )/../Fixtures/Symfony/app";

HOST=localhost
PORT=9999
API_PORT=9998
CURL_REQUEST_TEST=http://${HOST}:${PORT}/test/replaced/content
CURL_REQUEST_RELOAD="http://${HOST}:${API_PORT}/api/server"
ORIGINAL_TEXT="Wrong response!";
RESPONSE_TEXT_1="Hello world!";
RESPONSE_TEXT_2="Hello world from updated by PATCH /api/server request!"
CONTROLLER_TEMPLATE_FILE='../TestBundle/Controller/ReplacedContentTestController.php.tmpl'
CONTROLLER_FILE='../TestBundle/Controller/ReplacedContentTestController.php'
EXIT_CODE=0

TEMPLATE=$(< ${CONTROLLER_TEMPLATE_FILE})

# Place initial controller
CONTENTS=${TEMPLATE//"%REPLACE%"/$RESPONSE_TEXT_1}
echo "$CONTENTS" > ${CONTROLLER_FILE};

./console swoole:server:start --port=${PORT} --ansi --api --api-port=${API_PORT}

echo "[Info] Executing cURL Request: $CURL_REQUEST_TEST";
RESULT=$(curl ${CURL_REQUEST_TEST} -s)
echo "[Info] Result: $RESULT";
if [[ "$RESULT" != "$RESPONSE_TEXT_1" ]]; then
    echo "[Test] FAIL";
    exit 2;
fi


# Replace controller content
sleep 1;
CONTENTS=${TEMPLATE//"%REPLACE%"/$RESPONSE_TEXT_2}
echo "$CONTENTS" > ${CONTROLLER_FILE};
sleep 1;

EXPECTED_STATUS_CODE=204
echo "[Info] Executing cURL Request: $CURL_REQUEST_RELOAD";
RESULT="$(curl -X PATCH ${CURL_REQUEST_RELOAD} -i -s | head -n 1 | awk '{print $2}')"
echo "[Info] Result: $RESULT";
if [[ "$RESULT" = "$EXPECTED_STATUS_CODE" ]]; then
    echo "[Test] OK";
else
    echo "[Test] FAIL";
    EXIT_CODE=1;
fi

if [[ "$EXIT_CODE" != "0" ]]; then
    exit ${EXIT_CODE};
fi

sleep 1;

echo "[Info] Executing cURL Request: $CURL_REQUEST_TEST";
RESULT=$(curl ${CURL_REQUEST_TEST} -s)
echo "[Info] Result: $RESULT";
if [[ "$RESULT" = "$RESPONSE_TEXT_2" ]]; then
    echo "[Test] OK";
else
    echo "[Test] FAIL";
    EXIT_CODE=1;
fi

./console swoole:server:stop --ansi

CONTENTS=${TEMPLATE//"%REPLACE%"/$ORIGINAL_TEXT}
echo "$CONTENTS" > ${CONTROLLER_FILE};

exit ${EXIT_CODE};
