<#
.SYNOPSIS
    PowerShell client that sends crafted POST requests mimicking Cobalt Strike Beacon activity.

.DESCRIPTION
    This script simulates Cobalt Strike beacon traffic for testing NDR solutions.
    It can operate in single-request mode or continuous beacon mode with configurable
    intervals and jitter for realistic C2 traffic patterns.

.PARAMETER Domain
    Target domain/IP with optional port (default: localhost:8000)

.PARAMETER Url
    Full URL for the request (overrides -Domain if provided)

.PARAMETER Id
    Numeric ID to append as query parameter (default: 12345)

.PARAMETER CookieValue
    Custom Base64-encoded cookie value (defaults to randomly generated 128+ chars)

.PARAMETER BeaconMode
    Enable continuous beacon mode with periodic check-ins

.PARAMETER Duration
    Duration to run in beacon mode in minutes (default: 5.0)

.PARAMETER Interval
    Sleep interval between beacons in seconds (default: 60.0)

.PARAMETER Jitter
    Jitter percentage for interval randomization 0-100 (default: 0)

.EXAMPLE
    .\cobalt_client.ps1
    Single request to default localhost:8000

.EXAMPLE
    .\cobalt_client.ps1 -BeaconMode -Duration 10 -Interval 30
    Beacon mode for 10 minutes with 30 second intervals

.EXAMPLE
    .\cobalt_client.ps1 -BeaconMode -Domain "192.168.1.100:8080" -Interval 60 -Jitter 20
    Beacon mode with 60 second intervals and 20% jitter
#>

[CmdletBinding()]
param(
    [string]$Domain = "localhost:8000",
    [string]$Url = "",
    [int]$Id = 12345,
    [string]$CookieValue = "",
    [switch]$BeaconMode,
    [double]$Duration = 5.0,
    [double]$Interval = 60.0,
    [double]$Jitter = 0.0
)

# Build payload with null bytes and random data
function Build-Payload {
    # Start with 4 null bytes (typical Cobalt Strike pattern)
    $payload = [byte[]]::new(64)
    $payload[0] = 0x00
    $payload[1] = 0x00
    $payload[2] = 0x00
    $payload[3] = 0x00
    
    # Fill remaining 60 bytes with random data (simulating encrypted beacon data)
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $randomBytes = [byte[]]::new(60)
    $rng.GetBytes($randomBytes)
    [Array]::Copy($randomBytes, 0, $payload, 4, 60)
    
    return $payload
}

# Generate random Base64 cookie (128+ characters)
function New-CookieValue {
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $randomBytes = [byte[]]::new(96)  # 96 bytes -> 128 Base64 chars
    $rng.GetBytes($randomBytes)
    return [Convert]::ToBase64String($randomBytes)
}

# Send POST request to C2 endpoint
function Send-BeaconRequest {
    param(
        [string]$TargetUrl,
        [string]$Cookie,
        [bool]$Verbose = $true
    )
    
    try {
        # Build payload
        $payload = Build-Payload
        
        if ($Verbose) {
            Write-Host "Sending POST to $TargetUrl"
            Write-Host "Payload length: $($payload.Length) bytes"
            Write-Host "Cookie length: $($Cookie.Length) characters"
        } else {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Write-Host "[$timestamp] Beacon check-in -> $TargetUrl"
        }
        
        # Use WebRequest for more control over the request (better for NDR detection)
        # This method sends raw binary data more reliably than Invoke-WebRequest
        $request = [System.Net.HttpWebRequest]::Create($TargetUrl)
        $request.Method = "POST"
        $request.ContentType = "application/octet-stream"
        $request.UserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36"
        $request.Headers.Add("Cookie", "sessionid=$Cookie")
        $request.ContentLength = $payload.Length
        $request.Timeout = 10000
        
        # Write payload to request stream
        $requestStream = $request.GetRequestStream()
        $requestStream.Write($payload, 0, $payload.Length)
        $requestStream.Close()
        
        # Get response
        $response = $request.GetResponse()
        $responseStream = $response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($responseStream)
        $responseBody = $reader.ReadToEnd()
        
        if ($Verbose) {
            Write-Host "Response status: $($response.StatusCode) ($([int]$response.StatusCode))"
            if ($responseBody) {
                Write-Host "Response body: $responseBody"
            }
        } else {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Write-Host "[$timestamp] Response: $([int]$response.StatusCode) ($($response.ContentLength) bytes)"
        }
        
        $reader.Close()
        $responseStream.Close()
        $response.Close()
        
        return $true
    }
    catch [System.Net.WebException] {
        $errorResponse = $_.Exception.Response
        if ($errorResponse) {
            Write-Host "[ERROR] Request failed: HTTP $([int]$errorResponse.StatusCode)" -ForegroundColor Red
        } else {
            Write-Host "[ERROR] Request failed: $($_.Exception.Message)" -ForegroundColor Red
        }
        return $false
    }
    catch {
        Write-Host "[ERROR] Request failed: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

# Main execution
function Main {
    # Build URL
    $targetUrl = if ($Url) {
        $Url
    } else {
        "http://$Domain/submit.php"
    }
    
    # Append ID parameter if not present
    if ($targetUrl -notmatch "id=") {
        $delimiter = if ($targetUrl -match "\?") { "&" } else { "?" }
        $targetUrl = "$targetUrl$delimiter`id=$Id"
    }
    
    # Generate or use provided cookie
    $cookie = if ($CookieValue) {
        $CookieValue
    } else {
        New-CookieValue
    }
    
    if ($BeaconMode) {
        # Beacon mode: continuous periodic check-ins
        Write-Host ("=" * 60)
        Write-Host "BEACON MODE ACTIVATED" -ForegroundColor Green
        Write-Host ("=" * 60)
        Write-Host "Target URL: $targetUrl"
        Write-Host "Duration: $Duration minutes"
        Write-Host "Interval: $Interval seconds"
        Write-Host "Jitter: $Jitter%"
        Write-Host "Cookie length: $($cookie.Length) characters"
        Write-Host ("=" * 60)
        
        $endTime = (Get-Date).AddMinutes($Duration)
        $beaconCount = 0
        
        try {
            while ((Get-Date) -lt $endTime) {
                $beaconCount++
                Write-Host "`n--- Beacon #$beaconCount ---" -ForegroundColor Cyan
                
                $success = Send-BeaconRequest -TargetUrl $targetUrl -Cookie $cookie -Verbose $false
                
                # Calculate sleep time with jitter
                $sleepTime = $Interval
                if ($Jitter -gt 0) {
                    $jitterRange = $Interval * ($Jitter / 100.0)
                    $random = Get-Random -Minimum 0.0 -Maximum 1.0
                    $jitterValue = ($random * $jitterRange * 2) - $jitterRange
                    $sleepTime = [Math]::Max(1, $Interval + $jitterValue)
                }
                
                $remaining = ($endTime - (Get-Date)).TotalSeconds
                if ($remaining -le 0) {
                    break
                }
                
                $actualSleep = [Math]::Min($sleepTime, $remaining)
                $nextBeacon = (Get-Date).AddSeconds($actualSleep)
                Write-Host "Sleeping $([Math]::Round($actualSleep, 1))s until next beacon (@ $($nextBeacon.ToString('HH:mm:ss')))" -ForegroundColor Gray
                Start-Sleep -Seconds $actualSleep
            }
        }
        catch {
            Write-Host "`n[!] Beacon mode interrupted: $($_.Exception.Message)" -ForegroundColor Yellow
        }
        
        Write-Host "`n$("=" * 60)"
        Write-Host "Beacon mode completed. Total beacons sent: $beaconCount" -ForegroundColor Green
        Write-Host ("=" * 60)
    } else {
        # Single request mode
        Write-Host "Generated cookie value ($($cookie.Length) chars): $($cookie.Substring(0, 40))..."
        Send-BeaconRequest -TargetUrl $targetUrl -Cookie $cookie -Verbose $true | Out-Null
    }
}

# Run main function
Main
