echo off

set root=..\..
set target=%root%\js

rd /Q /S build

del /Q "%target%\common.js"
del /Q "%target%\editor.js"
del /Q "%target%\game-en.js"
del /Q "%target%\game-ru.js"
del /Q "%target%\game-ui.js"
del /Q "%target%\game.js"
del /Q "%target%\labels_en.js"
del /Q "%target%\labels_ru.js"
del /Q "%target%\md5.js"
del /Q "%target%\mr.js"
del /Q "%target%\fileprogress.js"

echo Android files
set target=%root%\standalone\android\project\assets

del /Q "%target%\js\common.js"
del /Q "%target%\js\editor.js"
del /Q "%target%\js\game-en.js"
del /Q "%target%\js\game-ru.js"
del /Q "%target%\js\game-ui.js"
del /Q "%target%\js\game.js"
del /Q "%target%\js\labels_en.js"
del /Q "%target%\js\labels_ru.js"
del /Q "%target%\js\md5.js"
del /Q "%target%\js\local.js"

del /Q "%target%\js\jquery-ui.min.js"
del /Q "%target%\js\jquery.min.js"
del /Q "%target%\jquery-ui.css"

del /Q "%target%\images\warn.png"
del /Q "%target%\images\suicide.png"
del /Q "%target%\images\delete.png"
del /Q "%target%\images\resume.png"
del /Q "%target%\images\dec.png"
del /Q "%target%\images\inc.png"
del /Q "%target%\images\pause.png"
del /Q "%target%\images\resume.png"
del /Q "%target%\images\save.png"
del /Q "%target%\images\create.png"
del /Q "%target%\images\user.png"
del /Q "%target%\images\loading.gif"

del /Q "%target%\sound\10sec.mp3"
del /Q "%target%\sound\end.mp3"
