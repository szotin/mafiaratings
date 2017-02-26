echo off
set root=..\..

echo Web files
set target=%root%\js
copy build\common.js "%target%"
copy build\editor.js "%target%"
copy build\game-en.js "%target%"
copy build\game-ru.js "%target%"
copy build\game-ui.js "%target%"
copy build\game.js "%target%"
copy build\labels_en.js "%target%"
copy build\labels_ru.js "%target%"
copy build\md5.js "%target%"
copy build\mr.js "%target%"
copy build\fileprogress.js "%target%"

echo Android files
set target=%root%\standalone\android\project\assets

copy "build\common.js" "%target%\js"
copy "build\editor.js" "%target%\js"
copy "build\game-en.js" "%target%\js"
copy "build\game-ru.js" "%target%\js"
copy "build\game-ui.js" "%target%\js"
copy "build\game.js" "%target%\js"
copy "build\labels_en.js" "%target%\js"
copy "build\labels_ru.js" "%target%\js"
copy "build\md5.js" "%target%\js"
copy "build\local.js" "%target%\js"

copy "%root%\js\jquery-ui.min.js" "%target%\js"
copy "%root%\js\jquery.min.js" "%target%\js"
copy "%root%\jquery-ui.css" "%target%"

copy "%root%\images\warn.png" "%target%\images\"
copy "%root%\images\suicide.png" "%target%\images\"
copy "%root%\images\delete.png" "%target%\images\"
copy "%root%\images\resume.png" "%target%\images\"
copy "%root%\images\dec.png" "%target%\images\"
copy "%root%\images\inc.png" "%target%\images\"
copy "%root%\images\pause.png" "%target%\images\"
copy "%root%\images\resume.png" "%target%\images\"
copy "%root%\images\save.png" "%target%\images\"
copy "%root%\images\create.png" "%target%\images\"
copy "%root%\images\user.png" "%target%\images\"
copy "%root%\images\loading.gif" "%target%\images\"

copy "%root%\sound\10sec.mp3" "%target%\sound\"
copy "%root%\sound\end.mp3" "%target%\sound\"

