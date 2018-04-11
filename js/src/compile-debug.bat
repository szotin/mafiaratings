echo off
mkdir build

copy common.js build
copy game-en.js build
copy game-ru.js build
copy game-ui.js build
copy game.js build
copy labels_en.js build
copy labels_ru.js build
copy md5.js build
copy mr.js build
copy mr.chart.js build
copy fileprogress.js build
copy local.js build
copy editor.js build

deploy.bat
