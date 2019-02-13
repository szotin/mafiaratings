echo off

rem  +--------------------------------------------------------------------------------------+
rem  | We are copying files with unicode characters instead of compiling because compiler   |
rem  | converts unicode characters to their codes (fe "\u0441"), which makes files rather   |
rem  | bigger than smaller.                                                                 |
rem  | Making files smaller is basically the main goal of compiling.                        |
rem  +--------------------------------------------------------------------------------------+

java -jar compiler.jar --js common.js --js_output_file ..\common.js
java -jar compiler.jar --js game-en.js --js_output_file ..\game-en.js
copy game-ru.js .. 
java -jar compiler.jar --js game-ui.js --js_output_file ..\game-ui.js
java -jar compiler.jar --js local.js --js_output_file ..\local.js
java -jar compiler.jar --js game.js --js_output_file ..\game.js
java -jar compiler.jar --js labels_en.js --js_output_file ..\labels_en.js
copy labels_ru.js ..
java -jar compiler.jar --js md5.js --js_output_file ..\md5.js
java -jar compiler.jar --js mr.js --js_output_file ..\mr.js
java -jar compiler.jar --js mr.chart.js --js_output_file ..\mr.chart.js
copy fileprogress.js ..
java -jar compiler.jar --js seating.js --js_output_file ..\seating.js
java -jar compiler.jar --js seating-en.js --js_output_file ..\seating-en.js
copy seating-ru.js ..
java -jar compiler.jar --js seating-ui.js --js_output_file ..\seating-ui.js

pause
