#!/bin/ash
# Define as variáveis para o servidor e arquivos de boot
SERVER_IP="server.ip"
SERVER_NAME="server.name"
# Exemplo usando netbootxyz
BOOT_BIOS="netboot.xyz.kpxe"
BOOT_UEFI="netboot.xyz.efi"
BOOT_ARM64="netboot.xyz-arm64.efi"

uci set dhcp.@dnsmasq[0].logdhcp='1'

# Configura os matches para identificar os clientes PXE

# Legacy (BIOS)
uci add dhcp match
uci set dhcp.@match[-1].networkid='bios'
uci set dhcp.@match[-1].match='60,PXEClient:Arch:00000'

# UEFI (x86/x64) – utilizando os códigos comuns
uci add dhcp match
uci set dhcp.@match[-1].networkid='efi'
uci set dhcp.@match[-1].match='60,PXEClient:Arch:00006'
uci add dhcp match
uci set dhcp.@match[-1].networkid='efi'
uci set dhcp.@match[-1].match='60,PXEClient:Arch:00007'
uci add dhcp match
uci set dhcp.@match[-1].networkid='efi'
uci set dhcp.@match[-1].match='60,PXEClient:Arch:00009'

# ARM64 – considerando que o firmware envia "PXEClient:Arch:000C"
uci add dhcp match
uci set dhcp.@match[-1].networkid='arm64'
uci set dhcp.@match[-1].match='60,PXEClient:Arch:000C'

# Configura os boot blocks associando os arquivos correspondentes

# Boot para Legacy (BIOS)
uci add dhcp boot
uci set dhcp.@boot[-1].filename="tag:bios,${BOOT_BIOS}"
uci set dhcp.@boot[-1].serveraddress="$SERVER_IP"
uci set dhcp.@boot[-1].servername="$SERVER_NAME"

# Boot para UEFI (x86/x64)
uci add dhcp boot
uci set dhcp.@boot[-1].filename="tag:efi,${BOOT_UEFI}"
uci set dhcp.@boot[-1].serveraddress="$SERVER_IP"
uci set dhcp.@boot[-1].servername="$SERVER_NAME"

# Boot para ARM64
uci add dhcp boot
uci set dhcp.@boot[-1].filename="tag:arm64,${BOOT_ARM64}"
uci set dhcp.@boot[-1].serveraddress="$SERVER_IP"
uci set dhcp.@boot[-1].servername="$SERVER_NAME"

uci commit dhcp
service dnsmasq reload
#Em caso de dúvida confira a documentação do OpenWRT: https://openwrt.org/docs/guide-user/base-system/dhcp_configuration#multi-arch_tftp_boot
