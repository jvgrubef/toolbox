@echo off
setlocal enabledelayedexpansion
chcp 65001
cls

wpeinit
echo Carregando, aguarde.
cls

rem Defina o servidor, onde quer montar e a letra da unidade que será listada:

set "server=\\pxe-server.lan\PXE\"
set "target_dir=Z:"

:netSetup
echo Buscando arquivos em %server%, aguarde.
net use %target_dir% %server%
cls

if errorlevel 1 (
    goto netError
) else (
    goto menu
)

:netError
echo Não foi possível montar a pasta %server% na unidade %target_dir%.
echo Verifique a conexão de rede ou as permissões de acesso.

rem Pergunta ao usuário se deseja tentar novamente ou sair
set /p choice="Deseja tentar novamente ou sair? (1 para tentar / 2 para sair): "

if /i "%choice%"=="1" (
    goto netSetup
) else if "%choice%"=="2" (
    goto end
) else (
    echo Opção invalida
    goto netError
)

:menu
rem Muda para o diretório alvo
cd /d "%target_dir%" || (
    echo O diretório não existe!
    exit /b
)

rem Inicia contagem
set count=0

rem Lista os diretórios e cria opções
echo Selecionar uma pasta seu corno:
echo -----------------------
for /d %%D in (*) do (
    if /i not "%%D"=="WinPE" (
        set /a count+=1
        echo !count! - %%D
        set "folder[!count!]=%%D"
    )
)

rem Verifica se existem pastas
if %count% equ 0 (
    echo Nenhuma pasta encontrada.
    pause
    exit /b
)

echo.
echo Opções:
echo 0 - Sair
set /p choice="Escolha uma pasta (1-%count%) ou 0 para sair: "

rem Verifica se o usuário quer sair
if "%choice%"=="0" (
    goto end
)

rem Verifica se a escolha é válida
if "!folder[%choice%]!"=="" (
    echo Opção inválida!
    goto menu
)

cls
rem Exibe a pasta escolhida
set "selected_folder=!folder[%choice%]!"

rem Verifica se setup.exe existe na pasta escolhida
if exist "!selected_folder!\setup.exe" (
    echo Você selecionou !selected_folder!, iniciando a instalação
    call "!selected_folder!\setup.exe"
) else (
    echo Não foi encontrado o instalador ^(setup.exe^) na pasta !selected_folder!.
)

echo.
goto menu

:end
echo Saindo, a maquina será reiniciada em breve.
wpeutil reboot
