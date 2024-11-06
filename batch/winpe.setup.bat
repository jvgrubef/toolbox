@echo off
title SK PXE AUX
setlocal EnableDelayedExpansion
chcp 65001
cls
echo. 
echo     ██████  ██ ▄█▀    ██▓███  ▒██   ██▒▓█████     ▄▄▄       █    ██ ▒██   ██▒
echo   ▒██    ▒  ██▄█▒    ▓██░  ██▒▒▒ █ █ ▒░▓█   ▀    ▒████▄     ██  ▓██▒▒▒ █ █ ▒░
echo   ░ ▓██▄   ▓███▄░    ▓██░ ██▓▒░░  █   ░▒███      ▒██  ▀█▄  ▓██  ▒██░░░  █   ░
echo     ▒   ██▒▓██ █▄    ▒██▄█▓▒ ▒ ░ █ █ ▒ ▒▓█  ▄    ░██▄▄▄▄██ ▓▓█  ░██░ ░ █ █ ▒ 
echo   ▒██████▒▒▒██▒ █▄   ▒██▒ ░  ░▒██▒ ▒██▒░▒████▒    ▓█   ▓██▒▒▒█████▓ ▒██▒ ▒██▒
echo   ▒ ▒▓▒ ▒ ░▒ ▒▒ ▓▒   ▒▓▒░ ░  ░▒▒ ░ ░▓ ░░░ ▒░ ░    ▒▒   ▓▒█░░▒▓▒ ▒ ▒ ▒▒ ░ ░▓ ░
echo   ░ ░▒  ░ ░░ ░▒ ▒░   ░▒ ░     ░░   ░▒ ░ ░ ░  ░     ▒   ▒▒ ░░░▒░ ░ ░ ░░   ░▒ ░
echo   ░  ░  ░  ░ ░░ ░    ░░        ░    ░     ░        ░   ▒    ░░░ ░ ░  ░    ░  
echo         ░  ░  ░                ░    ░     ░  ░         ░  ░   ░      ░    ░  
                                                                          
set "target_dir=Z:"

if "%1"=="" (
    wpeinit > nul
    net use %target_dir% /delete /y >nul 2>&1
    endlocal > nul
    start %~f0 "i" > nul
    echo.

    echo * Não feche ou clique em qualquer tecla dentro desta janela para manter o instalador em execução. 
    echo * Do contrario a maquina será reiniciada. O script extra é feito por causa do programa NET, que para ^(re^)montagem
    echo * precisa ser executado em um novo script
    echo.
    echo * Como funciona?
    echo * Você precisa ter um servidor Samba, e dentro da pasta compartilhada, devem ter as Iso's descompactada
    echo * em pastas separadas, este script vai montar a pasta selecionada em Z:, e então dentro dessa unidade
    echo * vai procurar o executável setup.exe para iniciar a instalação
    echo.
    echo * Exemplo:
    echo.
    echo ^> \\endereço-do-servidor.local\
    echo     └─ windows-files
    echo        └─ windows_10^(x64^)
    echo        ^|   └─ setup.exe e o restante dos arquivos da Iso descompactada.
    echo        └─ windows_11^(x64^)
    echo            └─ setup.exe e a mesma coisa do de cima.

    pause >nul
    goto end
)

title SK PXE AUX - Installer
set "server_standard=\\10.0.100.8\PXE\"
set "server=%server_standard%"
set "username="
set "password="
set "errorMenu="
set "errorMsg="
set "nextStep="
set "setServer= (deixe em branco para não alterar)"
:storageSetup
cls

echo Configuração de armazenamento:
echo.

if "%server_standard%"=="" (
    set "setServer="
    goto netData
)

echo Atual:
echo Servidor: %server_standard%

if not "%username%"=="" (
    echo Usuário: %username%
) else (
    echo Usuário definido: Não
)

if not "%password%"=="" (
    echo Senha definida: Sim
) else (
    echo Senha definida: Não
)

echo.

set /p customChoice="Deseja customizar o caminho do servidor, usuário e senha? (S/N): "
if /i "%customChoice%"=="S" (
    goto netData
) else if /i "%customChoice%"=="N" (
    goto netSetup
) else (
    echo Opção inválida
    goto storageSetup
)

:netData
set /p server="Digite o caminho do servidor no formato: \\X.X.X.X\pasta\%setServer%: "

if "%server%"=="" (
    if "%setServer%"=="" (
        cls
        echo Configuração de armazenamento:
        echo.
        echo É necessário definir o servidor
        goto netData
    )
    set "server=%server_standard%"
) else (
    set "server_standard=%server%"
)

set /p username="Digite o nome de usuário (deixe em branco se não precisar): "

if "%username%"=="" (
    set "password="
    goto netSetup
)

set /p password="Digite a senha (deixe em branco se não precisar): "

:netSetup
cls

echo A nova unidade %target_dir% está sendo montada.
echo Buscando arquivos em %server%, aguarde.
echo.

if not "%username%"=="" (
    echo Usuário: %username%

    if not "%password%"=="" (
        echo Senha definida: Sim
    ) else (
        echo Senha definida: Não
    )

    net use %target_dir% %server% /user:%username% %password%
) else (
    echo Usuário definido: Não
    echo Senha definida: Não

    net use %target_dir% %server%
)

if errorlevel 1 (
    set "errorMsg=Erro ao montar a pasta %server% na unidade %target_dir%. Verifique a conexão de rede ou as permissões de acesso."
    set "nextStep=storageSetup"
    goto errorHandling
)

cls

:menu
if not "%errorMenu%"=="" (
    cls
    echo %errorMenu%
    echo.
)

cd /d "%target_dir%" || (
    set "errorMsg=O diretório não existe ou não está acessível."
    set "nextStep=menu"
    goto errorHandling
)

set count=0

echo Selecionar uma pasta:
echo -----------------------
for /d %%D in (*) do (
    if /i not "%%D"=="WinPE" (
        set /a count+=1
        echo └─ !count! - %%D
        set "folder[!count!]=%%D"
    )
)

if %count% equ 0 (
    set "errorMsg=Nenhuma pasta encontrada."
    set "nextStep=menu"
    goto errorHandling
)

echo.
echo Opções:
echo -----------------------
echo └─ S - Sair
echo └─ R - Recarregar lista
echo └─ V - Voltar a configuração de servidor
echo.
set /p choiceInstaller="Escolha uma pasta (1-%count%) ou uma das opções acima: "

if /i "%choiceInstaller%"=="S" (
    goto end
)
if /i "%choiceInstaller%"=="R" (
    cls
    goto menu
)
if /i "%choiceInstaller%"=="V" (
    goto reset
)

if "!folder[%choiceInstaller%]!"=="" (
    set "errorMenu=Opção inválida! Por favor, escolha uma pasta válida."
    goto menu
)

cls
set "selected_folder=!folder[%choiceInstaller%]!"

if exist "!selected_folder!\setup.exe" (
    echo Você selecionou !selected_folder!, iniciando a instalação
    call "!selected_folder!\setup.exe"
) else (
    set "errorMsg=Não foi encontrado o instalador (setup.exe) na pasta !selected_folder!."
    set "nextStep=menu"
    goto errorHandling
)

goto menu

:errorHandling
echo %errorMsg%
set /p choiceError="Deseja tentar novamente ou sair? (S/N): "

if /i "%choiceError%"=="S" (
    goto %nextStep%
) else if /i "%choiceError%"=="N" (
    goto end
) else (
    echo Opção inválida.
    goto errorHandling
)

:reset
net use %target_dir% /delete /y
endlocal
start %~f0 "i" >nul
cls
echo Nos vemos em breve.
exit

:end
echo Saindo, a máquina será reiniciada em breve. Bye bye!
echo %date% - %time%
endlocal
wpeutil reboot
