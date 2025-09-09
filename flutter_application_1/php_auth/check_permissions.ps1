$file = "firebase-credentials.json"
$acl = Get-Acl $file
Write-Host "File: $file"
Write-Host "Owner: $($acl.Owner)"
Write-Host "Access Rules:"
$acl.Access | Format-Table IdentityReference,FileSystemRights,AccessControlType,IsInherited -AutoSize
