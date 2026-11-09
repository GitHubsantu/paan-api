# Android APK Release Runbook

Use this runbook to publish an Android APK that customers can download from the landing page.

## Before you start

- Build a signed release APK.
- Increment `versionCode` for every release. The API rejects a duplicate version code.
- Have the admin phone number and password available.
- Never put the admin password or access token in source control, chat, or a script committed to the repository.

## 1. Build the APK

Run the Android project's release build command. Confirm the output is an `.apk` file, not an `.aab` file.

## 2. Sign in as an administrator

In PowerShell, replace the placeholder values and run:

```powershell
$adminLogin = curl.exe -sS -X POST "https://paan-api.piratemods.com/api/v1/admin/login" `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d '{\"phone\":\"YOUR_ADMIN_PHONE\",\"password\":\"YOUR_ADMIN_PASSWORD\"}'

$token = ($adminLogin | ConvertFrom-Json).data.token
```

Do not print or share `$token`. It grants administrator access.

## 3. Upload the release

Set the APK location, version details, and release notes, then upload it:

```powershell
$apk = "C:\path\to\app-release.apk"

$upload = curl.exe -sS -X POST "https://paan-api.piratemods.com/api/v1/admin/app-releases" `
  -H "Authorization: Bearer $token" `
  -H "Accept: application/json" `
  -F "apk=@$apk" `
  -F "version_name=1.0.1" `
  -F "version_code=5" `
  -F "release_notes=Bug fixes and performance improvements." `
  -F "min_android=8.0" `
  -F "is_published=1"

$release = $upload | ConvertFrom-Json
$releaseId = $release.data.id
$release
```

The upload validates that the file is an APK, stores it privately, and records its SHA-256 checksum. The maximum size is configured with `APK_MAX_MB` (150 MB by default).

## 4. Make the release current

Only one Android release can be current. This selects the uploaded release for downloads:

```powershell
curl.exe -sS -X POST "https://paan-api.piratemods.com/api/v1/admin/app-releases/$releaseId/make-current" `
  -H "Authorization: Bearer $token" `
  -H "Accept: application/json"
```

## 5. Enable APK downloads on the landing page

```powershell
curl.exe -sS -X PUT "https://paan-api.piratemods.com/api/v1/admin/download-settings" `
  -H "Authorization: Bearer $token" `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d '{\"download_mode\":\"apk\"}'
```

## 6. Verify the release

Open the landing page and confirm the version and APK download button appear. Download from:

```text
https://paan-api.piratemods.com/download
```

Install the APK on a test device and confirm the version shown in Android matches the release. You can compare the file checksum shown on the landing page with the locally calculated value:

```powershell
Get-FileHash $apk -Algorithm SHA256
```

The landing page cache may take up to 60 seconds to refresh after changing release settings.

## Roll back to a previous release

1. List releases with `GET /api/v1/admin/app-releases` using the same Bearer token.
2. Make the previous published release current using its release ID and the command in step 4.
3. Verify the landing page again.

Do not delete the current release. The API prevents it; first make another published release current.
