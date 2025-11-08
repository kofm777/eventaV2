# Create Laravel directory structure
$directories = @(
    "backend\app\Http\Controllers",
    "backend\app\Http\Middleware", 
    "backend\app\Models",
    "backend\app\Mail",
    "backend\app\Console",
    "backend\app\Exceptions",
    "backend\config",
    "backend\database\migrations",
    "backend\database\seeders",
    "backend\database\factories",
    "backend\routes",
    "backend\resources\views\emails",
    "backend\storage\app",
    "backend\storage\framework\cache",
    "backend\storage\framework\sessions",
    "backend\storage\framework\views",
    "backend\storage\logs",
    "backend\tests\Feature",
    "backend\tests\Unit",
    "backend\public"
)

foreach ($dir in $directories) {
    New-Item -ItemType Directory -Path $dir -Force
    Write-Host "Created: $dir"
}

Write-Host "Directory structure created successfully!"
