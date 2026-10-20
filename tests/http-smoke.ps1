param(
    [string]$BaseUrl = 'http://localhost/BaiTapNhomPHP/public',
    [switch]$SkipDatabase
)

# Only GET requests, invalid registration and unsuccessful login.
# Does not create accounts or alter the application database.
Add-Type -AssemblyName System.Net.Http
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.AllowAutoRedirect = $false
$handler.CookieContainer = New-Object System.Net.CookieContainer
$client = New-Object System.Net.Http.HttpClient($handler)
$client.Timeout = [TimeSpan]::FromSeconds(15)
$checks = 0

function Check-Status($Response, [int]$Expected) {
    if ([int]$Response.StatusCode -ne $Expected) {
        throw "Unexpected HTTP status: $([int]$Response.StatusCode), expected $Expected"
    }
    $script:checks++
}

function Post-Form([string]$Route, [hashtable]$Fields) {
    $values = New-Object 'System.Collections.Generic.Dictionary[string,string]'
    foreach ($key in $Fields.Keys) { $values.Add($key, [string]$Fields[$key]) }
    $content = New-Object System.Net.Http.FormUrlEncodedContent($values)
    try {
        return $client.PostAsync("$BaseUrl/index.php?route=$Route", $content).GetAwaiter().GetResult()
    } finally {
        $content.Dispose()
    }
}

try {
    $response = $client.GetAsync("$BaseUrl/index.php?route=/login").GetAwaiter().GetResult()
    Check-Status $response 200
    $html = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $token = [regex]::Match($html, 'name="_token" value="([a-f0-9]{64})"').Groups[1].Value
    if (-not $token) { throw 'Missing CSRF token' }
    if (-not ($response.Headers.GetValues('Set-Cookie') -join ';').Contains('HttpOnly')) {
        throw 'Missing HttpOnly session cookie'
    }
    Check-Status ($client.GetAsync("$BaseUrl/login").GetAwaiter().GetResult()) 200
    Check-Status ($client.GetAsync("$BaseUrl/index.php?route=/register").GetAwaiter().GetResult()) 200
    Check-Status ($client.GetAsync("$BaseUrl/index.php?route=/missing").GetAwaiter().GetResult()) 404
    Check-Status ($client.GetAsync("$BaseUrl/index.php?route=/logout").GetAwaiter().GetResult()) 405
    Check-Status ($client.GetAsync("$BaseUrl/index.php?route=/account").GetAwaiter().GetResult()) 303
    Check-Status ($client.GetAsync("$BaseUrl/index.php?route=/admin").GetAwaiter().GetResult()) 303
    Check-Status (Post-Form '/login' @{username='none'; password='bad'}) 403
    Check-Status (Post-Form '/register' @{_token='incorrect'}) 403
    $invalid = Post-Form '/register' @{
        _token=$token; username='<script>alert(1)</script>'; name='Test';
        phone='invalid'; email='invalid'; password='short'; password_confirmation='different'
    }
    Check-Status $invalid 422
    $body = $invalid.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($body.Contains('<script>alert(1)</script>') -or -not $body.Contains('&lt;script&gt;')) {
        throw 'Registration output was not escaped'
    }
    if ($body.Contains('value="short"')) { throw 'Password was echoed' }
    foreach ($field in @('username', 'phone', 'email', 'password', 'password_confirmation')) {
        if (-not $body.Contains("id=`"$field-error`"")) { throw "Missing inline error: $field" }
        if (-not $body.Contains("aria-describedby=`"$field-error`"")) { throw "Missing accessible field error: $field" }
    }
    $emptyLogin = Post-Form '/login' @{_token=$token; username=''; password=''}
    Check-Status $emptyLogin 422
    $emptyBody = $emptyLogin.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if (-not $emptyBody.Contains('id="username-error"') -or -not $emptyBody.Contains('id="password-error"')) {
        throw 'Missing required login field errors'
    }
    # Nonexistent test username; only reads database, never creates an account.
    if (-not $SkipDatabase) {
        $failed = Post-Form '/login' @{_token=$token; username='__nonexistent_auth_smoke__'; password='wrong'}
        Check-Status $failed 422
    }
    $projectUrl = $BaseUrl -replace '/public/?$', ''
    if ($projectUrl -ne $BaseUrl) {
        Check-Status ($client.GetAsync("$projectUrl/.env").GetAwaiter().GetResult()) 403
        Check-Status ($client.GetAsync("$projectUrl/database/quanly_chuoi_san_the_thao.sql").GetAwaiter().GetResult()) 403
    }
    Check-Status (Post-Form '/logout' @{_token=$token}) 303
    Check-Status (Post-Form '/logout' @{_token=$token}) 403
    Write-Output "PASS: $checks HTTP status checks plus CSRF, cookie and HTML checks. No database records created."
    if ($SkipDatabase) { Write-Output 'Database-dependent login check was explicitly skipped.' }
} finally {
    $client.Dispose()
    $handler.Dispose()
}
