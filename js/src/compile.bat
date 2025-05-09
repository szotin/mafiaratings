echo off

rem  +--------------------------------------------------------------------------------------+
rem  | We are copying files with unicode characters instead of compiling because compiler   |
rem  | converts unicode characters to their codes (fe "\u0441"), which makes files rather   |
rem  | bigger than smaller.                                                                 |
rem  | Making files smaller is basically the main goal of compiling.                        |
rem  +--------------------------------------------------------------------------------------+

echo 1 common.js
java -jar compiler.jar --js common.js --js_output_file ..\common.js
echo 2 game-ui.js
java -jar compiler.jar --js game-ui.js --js_output_file ..\game-ui.js
echo 3 local.js
java -jar compiler.jar --js local.js --js_output_file ..\local.js
echo 4 game.js
java -jar compiler.jar --js game.js --js_output_file ..\game.js
echo 5 md5.js
java -jar compiler.jar --js md5.js --js_output_file ..\md5.js
echo 6 mr.js
java -jar compiler.jar --js mr.js --js_output_file ..\mr.js
echo 7 mr.chart.js
java -jar compiler.jar --js mr.chart.js --js_output_file ..\mr.chart.js
echo 8 seating.js
java -jar compiler.jar --js seating.js --js_output_file ..\seating.js
echo 9 seating-ui.js
java -jar compiler.jar --js seating-ui.js --js_output_file ..\seating-ui.js
echo 10 scoring_editor.js
java -jar compiler.jar --js scoring_editor.js --js_output_file ..\scoring_editor.js
echo 11 normalizer_editor.js
java -jar compiler.jar --js normalizer_editor.js --js_output_file ..\normalizer_editor.js
copy labels_*.js ..
copy game_*.js .. 
copy seating_*.js ..

pause
