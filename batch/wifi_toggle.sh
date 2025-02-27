#!/bin/sh

# Verifica se foram passados os argumentos corretos
if [ "$#" -ne 2 ]; then
    echo "Uso: $0 <on|off> <SSID>"
    exit 1
fi

ACTION=$1
SSID=$2

# Define o estado com base no argumento
if [ "$ACTION" = "on" ]; then
    STATE=0  # Ativar Wi-Fi
elif [ "$ACTION" = "off" ]; then
    STATE=1  # Desativar Wi-Fi
else
    echo "Erro: O primeiro parâmetro deve ser 'on' ou 'off'."
    exit 1
fi

WIFINET=$(uci show wireless | grep "ssid='$SSID'" | cut -d'.' -f2 | cut -d'=' -f1)

if [ -n "$WIFINET" ]; then
    uci set wireless.$WIFINET.disabled=$STATE
    uci commit wireless
    wifi reload
else
    echo "SSID '$SSID' não encontrado!"
fi
