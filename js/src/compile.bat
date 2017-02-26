echo off
mkdir build

java -jar compiler.jar --js common.js --js_output_file build\common.js
java -jar compiler.jar --js game.js --js_output_file build\game.js
java -jar compiler.jar --js game-en.js --js_output_file build\game-en.js
copy game-ru.js build
java -jar compiler.jar --js game-ui.js --js_output_file build\game-ui.js
java -jar compiler.jar --js labels_en.js --js_output_file build\labels_en.js
copy labels_ru.js build
java -jar compiler.jar --js md5.js --js_output_file build\md5.js
java -jar compiler.jar --js mr.js --js_output_file build\mr.js
copy fileprogress.js build
java -jar compiler.jar --js local.js --js_output_file build\local.js
java -jar compiler.jar --js editor.js --js_output_file build\editor.js

deploy.bat

pause
