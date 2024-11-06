@echo off
setlocal enabledelayedexpansion
chcp 65001
cls

wpeinit
echo Carregando, aguarde.
cls 

set "server_standard=\\10.0.100.8\PXE\"
set "target_dir=Z:"
set "username="
set "password="

:storageSetup

echo Configuração de armazenamento:
echo .
echo Atual:
echo Servidor: %server%.

if not "%username%"=="" (
    echo Usuário: %username%
) else (
    echo Usuário: não definido
)

if not "%password%"=="" (
    echo Senha definida: Sim
) else (
    echo Senha definida: Não
)

set /p customChoice="Deseja customizar o caminho do servidor, usuário e senha? (S/N): "
if /i "%customChoice%"=="S" (
    set /p server="Digite o caminho do servidor no formato: \\X.X.X.X\pasta\ (deixe em branco para não alterar): "

    if "%server%"=="" (
        set "server=%server_standard%"
    ) else (
        set "server_standard=%server%"
    )

    set /p username="Digite o nome de usuário (deixe em branco se não precisar): "

    if not "%username%"=="" (
        set /p password="Digite a senha (deixe em branco se não precisar): "
    ) else (
        set "password="
    )

) else if /i "%customChoice%"=="N" (
    goto netSetup
) else (
    echo Opção inválida
    goto storageSetup
)

:netSetup
cls
echo Buscando arquivos em %server%, aguarde.
if not "%username%"=="" (
    echo Usuário: %username%

    if not "%password%"=="" (
        echo Senha definida: Sim
    ) else (
        echo Senha definida: Não
    )

    net use %target_dir% %server% /user:%username% %password% /persistent:no >nul 2>&1
) else (
    echo Usuário: não definido
    echo Senha definida: Não

    net use %target_dir% %server% /persistent:no >nul 2>&1
)

if errorlevel 1 (
    set "errorMsg=Erro ao montar a pasta %server% na unidade %target_dir%. ^
    Verifique a conexão de rede ou as permissões de acesso."
    set "nextStep=storageSetup"
    goto errorHandling
) else (
    goto menu
)

:menu
cls
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
        echo !count! - %%D
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
echo S - Sair
echo R - Recarregar lista
set /p choiceInstaller="Escolha uma pasta (1-%count%) ou 0 para sair: "

if /i "%choiceInstaller%"=="S" (
    goto end
)
if /i "%choiceInstaller%"=="R" (
    goto menu
)

echo %choiceInstaller% | findstr /r "^[1-9][0-9]*$" >nul
if errorlevel 1 (
    echo Opção inválida! Por favor, escolha S para sair, R para recarregar oU número de uma pasta existente.
    goto menu
)

if "%choiceInstaller%" lss 1 if "%choiceInstaller%" gtr %count% (
    echo Opção inválida! Por favor, escolha o número de uma pasta existente.
    goto menu
)

if "!folder[%choiceInstaller%]!"=="" (
    echo Opção inválida! Por favor, escolha uma pasta válida.
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

echo.
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

:end
echo Saindo, a maquina será reiniciada em breve. Bye bye!
echo %date% %time%
wpeutil reboot
