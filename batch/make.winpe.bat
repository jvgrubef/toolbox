@echo off
setlocal
cls

copype amd64 C:\WinPE_amd64
Dism /Get-ImageInfo /ImageFile:"C:\WinPE_amd64\media\sources\boot.wim"
Dism /Mount-Image /ImageFile:"C:\WinPE_amd64\media\sources\boot.wim" /Name:"Microsoft Windows PE (amd64)" /MountDir:C:\WinPE_amd64\mount

Dism /Image:c:\WinPE_amd64\mount /Set-InputLocale:pt-br

Dism /Image:C:\WinPE_amd64\mount /Add-Package /PackagePath:"C:\Program Files (x86)\Windows Kits\10\Assessment and Deployment Kit\Windows Preinstallation Environment\amd64\WinPE_OCs\WinPE-WMI.cab"
Dism /Image:C:\WinPE_amd64\mount /Add-Package /PackagePath:"C:\Program Files (x86)\Windows Kits\10\Assessment and Deployment Kit\Windows Preinstallation Environment\amd64\WinPE_OCs\WinPE-SecureStartup.cab"
Dism /Image:C:\WinPE_amd64\mount /Add-Package /PackagePath:"C:\Program Files (x86)\Windows Kits\10\Assessment and Deployment Kit\Windows Preinstallation Environment\amd64\WinPE_OCs\PT-br\WinPE-SecureStartup_PT-br.cab"

Dism /Unmount-Image /MountDir:"C:\WinPE_amd64\mount" /commit
MakeWinPEMedia /ISO C:\WinPE_amd64 C:\WinPE_amd64\WinPE_amd64.iso

exit
