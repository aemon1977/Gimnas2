param(
    [string]$RepoPath = "C:\github\Gimnas2",
    [string]$Branch = "master",
    [string]$CommitMessage = "Actualització automàtica",
    [switch]$SkipPull
)

function Resolve-GitPath {
    $gitCmd = Get-Command git -ErrorAction SilentlyContinue
    if (-not $gitCmd) {
        throw "No s'ha trobat Git al PATH. Instal·la Git per Windows."
    }
    return $gitCmd.Source
}

function Invoke-Git {
    param(
        [string]$GitExe,
        [string]$Repository,
        [string[]]$Arguments
    )
    $result = & $GitExe -C $Repository @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Error executant git $($Arguments -join ' '): $result"
    }
    return $result
}

if (-not (Test-Path $RepoPath)) {
    throw "El directori '$RepoPath' no existeix."
}

$gitExe = Resolve-GitPath

if (-not $SkipPull) {
    Write-Host "Actualitzant '$Branch' des d'origen..."
    Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("fetch", "origin")
    Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("checkout", $Branch)
    Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("pull", "--ff-only", "origin", $Branch)
} else {
    Write-Host "S'omet l'actualització remota perquè s'ha indicat -SkipPull."
}

$status = & $gitExe -C $RepoPath status --porcelain
if (-not $status) {
    Write-Host "No hi ha canvis per pujar."
    exit 0
}

Write-Host "Afegint canvis..."
Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("add", "-A")

Write-Host "Creant commit..."
Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("commit", "-m", $CommitMessage)

Write-Host "Enviant a origin/$Branch..."
Invoke-Git -GitExe $gitExe -Repository $RepoPath -Arguments @("push", "origin", $Branch)

Write-Host "Actualització completada."
