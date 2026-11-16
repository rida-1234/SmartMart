; Smart Mart POS installer (Inno Setup 6). Compile on Windows after running deploy\build.ps1.
; The same installer does the first install and every update: the shop's database and .env
; are copied only if they don't exist, so updates never overwrite shop data.

#define AppVersion "1.0.0"

[Setup]
; Generate your own GUID once (Inno Setup: Tools > Generate GUID) and never change it.
AppId={{PUT-YOUR-OWN-GUID-HERE}
AppName=Smart Mart POS
AppVersion={#AppVersion}
AppPublisher=Smart Mart
DefaultDirName=C:\SmartMart
DisableDirPage=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
CloseApplications=force
OutputDir=..\dist
OutputBaseFilename=SmartMart-Setup-{#AppVersion}
Compression=lzma2
SolidCompression=yes
ArchitecturesInstallIn64BitMode=x64compatible

[Dirs]
Name: "{app}"; Permissions: users-modify

[Files]
Source: "..\dist\bundle\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\dist\bundle\app\*"; DestDir: "{app}\app"; Excludes: "\database\database.sqlite,\.env"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\dist\bundle\app\database\database.sqlite"; DestDir: "{app}\app\database"; Flags: onlyifdoesntexist uninsneveruninstall
Source: "..\dist\bundle\app\.env"; DestDir: "{app}\app"; Flags: onlyifdoesntexist uninsneveruninstall
Source: "..\dist\bundle\start.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\dist\bundle\stop.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\dist\bundle\backup.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\dist\bundle\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Icons]
Name: "{commondesktop}\Smart Mart"; Filename: "{app}\start.bat"; WorkingDir: "{app}"; IconFilename: "{app}\app\public\favicon.ico"
Name: "{commonstartup}\Smart Mart"; Filename: "{app}\start.bat"; WorkingDir: "{app}"

[Run]
Filename: "{app}\stop.bat"; Flags: runhidden waituntilterminated
Filename: "{tmp}\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; StatusMsg: "Installing runtime..."; Flags: waituntilterminated
Filename: "{app}\backup.bat"; StatusMsg: "Backing up shop data..."; Flags: runhidden waituntilterminated
Filename: "{app}\php\php.exe"; Parameters: "artisan migrate --force"; WorkingDir: "{app}\app"; StatusMsg: "Updating database..."; Flags: runhidden waituntilterminated
Filename: "{app}\php\php.exe"; Parameters: "artisan optimize:clear"; WorkingDir: "{app}\app"; Flags: runhidden waituntilterminated
Filename: "{app}\php\php.exe"; Parameters: "artisan optimize"; WorkingDir: "{app}\app"; Flags: runhidden waituntilterminated
; Change 22:00 to the shop's closing time.
Filename: "schtasks"; Parameters: "/create /f /tn ""SmartMart Daily Backup"" /tr ""{app}\backup.bat"" /sc daily /st 22:00"; Flags: runhidden
Filename: "{app}\start.bat"; Description: "Start Smart Mart now"; Flags: postinstall shellexec nowait

[UninstallRun]
Filename: "{app}\stop.bat"; Flags: runhidden; RunOnceId: "StopServer"
Filename: "schtasks"; Parameters: "/delete /f /tn ""SmartMart Daily Backup"""; Flags: runhidden; RunOnceId: "DeleteBackupTask"
