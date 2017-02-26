copy bin\Release\MafiaRatingsSetup.msi ..\..\..\downloads /Y
copy bin\Release\MafiaRatingsSetup.ru.msi ..\..\..\downloads /Y
copy bin\Release\MafiaRatings.exe ..\..\..\downloads\windows /Y
copy bin\Release\Launcher.exe ..\..\..\downloads\windows /Y
copy bin\Release\Updater.exe ..\..\..\downloads\windows /Y
mkdir ..\..\..\downloads\windows\ru
copy bin\Release\ru\MafiaRatings.resources.dll ..\..\..\downloads\windows\ru /Y
copy bin\Release\ru\Launcher.resources.dll ..\..\..\downloads\windows\ru /Y
copy bin\Release\ru\Updater.resources.dll ..\..\..\downloads\windows\ru /Y
