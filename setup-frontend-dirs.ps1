# Create Angular directory structure
$directories = @(
    "frontend\src\app\components\register",
    "frontend\src\app\components\admin-list",
    "frontend\src\app\components\scanner",
    "frontend\src\app\components\speaker-avatar",
    "frontend\src\app\components\login",
    "frontend\src\app\services",
    "frontend\src\app\models",
    "frontend\src\app\guards",
    "frontend\src\assets",
    "frontend\src\environments",
    "frontend\cypress\e2e",
    "frontend\cypress\fixtures",
    "frontend\cypress\support"
)

foreach ($dir in $directories) {
    New-Item -ItemType Directory -Path $dir -Force
    Write-Host "Created: $dir"
}

Write-Host "Frontend directory structure created successfully!"
