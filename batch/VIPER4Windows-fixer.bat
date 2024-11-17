@echo off
chcp 65001

>nul 2>&1 reg query "HKU\S-1-5-19\Environment"

if '%errorlevel%' NEQ '0' (
	(echo.Set UAC = CreateObject^("Shell.Application"^)&echo.UAC.ShellExecute "%~s0", "", "", "runas", 1)>"%temp%\getadmin.vbs"
	"%temp%\getadmin.vbs"
	exit /B
) else ( 
	>nul 2>&1 del "%temp%\getadmin.vbs" 
)

title "ViPER4Windows Fixer"
pushd "%~dp0"

for /f "tokens=2*" %%X in ('REG QUERY "HKEY_LOCAL_MACHINE\SOFTWARE\ViPER4Windows" /v ConfigPath') do set PAPPDIR=%%Y
set APPDIR=%PAPPDIR:\DriverComm=%

:CHOICE_MENU
cls
echo Opções
echo.
echo 1 - Patch de registro
echo 2 - Iniciar configurador do ViPER4Windows
echo 3 - Reinicie o serviço de áudio
echo 0 - Sair
echo.
echo Digite um número abaixo e pressione a tecla Enter.
echo É recomendado parar qualquer aplicativo que esteja emitindo áudio
echo.

set CMVAR=
set /p "CMVAR=Entre com a opção: "

if "%CMVAR%"=="0" exit

if not exist "%APPDIR%" (
	echo Falha - ViPER4Windows não está instalado.
	>nul 2>&1 timeout /t 2
	goto:CHOICE_MENU
)

if "%CMVAR%"=="1" (
	for %%a in (HKLM\SOFTWARE\Classes HKCR) do (
		>nul 2>&1 reg delete "%%a\AudioEngine\AudioProcessingObjects" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "FriendlyName" /t REG_SZ /d "ViPER4Windows" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "Copyright" /t REG_SZ /d "Copyright (C) 2013, vipercn.com" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MajorVersion" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MinorVersion" /t REG_DWORD /d "0" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "Flags" /t REG_DWORD /d "13" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MinInputConnections" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MaxInputConnections" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MinOutputConnections" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MaxOutputConnections" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "MaxInstances" /t REG_DWORD /d "4294967295" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "NumAPOInterfaces" /t REG_DWORD /d "1" /f
		>nul 2>&1 reg add "%%a\AudioEngine\AudioProcessingObjects\{DA2FB532-3014-4B93-AD05-21B2C620F9C2}" /v "APOInterface0" /t REG_SZ /d "{FD7F2B29-24D0-4B5C-B177-592C39F9CA10}" /f
	)

	>nul 2>&1 reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\AppCompatFlags\Layers" /v "%APPDIR%\ViPER4WindowsCtrlPanel.exe" /t REG_SZ /d "RUNASADMIN" /f
	
	echo Feito - Patch de registro aplicado.
	echo Recomendação - Reinicie o serviço de áudio.

	>nul 2>&1 timeout /t 2
	goto:CHOICE_MENU
)

if "%CMVAR%"=="2" (
	if exist "%APPDIR%\Configurator.exe" (
		start "" "%APPDIR%\Configurator.exe"
		goto:CHOICE_MENU
	) else (
		echo Falha - Configurador do ViPER4Windows não encontrado.
		>nul 2>&1 timeout /t 2
		goto:CHOICE_MENU
	)
)

if "%CMVAR%"=="3" (
	start "" powershell -command "Restart-Service -Name Audiosrv -Confirm:$false"
	echo Feito - Serviço de áudio está reiniciando.
	>nul 2>&1 timeout /t 2
	goto:CHOICE_MENU
)

goto:eof
