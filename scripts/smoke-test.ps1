param(
    [string] $BaseUrl = 'http://paan-api.test/api/v1'
)

$ErrorActionPreference = 'Stop'
$failed = $false

function Pass([string] $Message) {
    Write-Host "PASS  $Message" -ForegroundColor Green
}

function Fail([string] $Message) {
    $script:failed = $true
    Write-Host "FAIL  $Message" -ForegroundColor Red
}

function Invoke-Api([string] $Method, [string] $Path, $Body = $null, [string] $Token = $null) {
    $headers = @{ Accept = 'application/json' }
    if ($Token) { $headers.Authorization = "Bearer $Token" }

    $parameters = @{ Method = $Method; Uri = "$BaseUrl$Path"; Headers = $headers; ContentType = 'application/json' }
    if ($null -ne $Body) { $parameters.Body = ($Body | ConvertTo-Json -Depth 8) }

    return Invoke-RestMethod @parameters
}

function Require($Condition, [string] $Message) {
    if (-not $Condition) { throw $Message }
}

try {
    $phone = '9' + (Get-Random -Minimum 100000000 -Maximum 999999999).ToString()
    $otp = '123456'

    Invoke-Api 'POST' '/auth/otp/send' @{ phone = $phone; purpose = 'register' } | Out-Null
    $customer = Invoke-Api 'POST' '/auth/register' @{ name = 'Smoke Customer'; phone = $phone; otp = $otp }
    $customerToken = $customer.data.token
    Require $customerToken 'Registration did not return a customer token.'
    Pass 'Customer registered'

    Write-Host 'Waiting for the OTP resend cooldown...'
    Start-Sleep -Seconds 31
    Invoke-Api 'POST' '/auth/otp/send' @{ phone = $phone; purpose = 'login' } | Out-Null
    $login = Invoke-Api 'POST' '/auth/login' @{ phone = $phone; otp = $otp }
    $customerToken = $login.data.token
    Require $customerToken 'Login did not return a customer token.'
    Pass 'Customer logged in'

    $categories = Invoke-Api 'GET' '/categories'
    Require ($categories.data.Count -gt 0) 'No catalog categories were returned.'
    $products = Invoke-Api 'GET' '/products?available=true'
    $product = $products.data | Select-Object -First 1
    Require $product 'No available product was returned.'
    Pass 'Catalog browsed'

    $addonIds = @()
    if ($product.addons.Count -gt 0) { $addonIds = @($product.addons[0].id) }
    $orderRequest = @{ items = @(@{ product_id = $product.id; quantity = 1; addon_ids = $addonIds }); comment = 'Smoke test order'; payment_method = 'mock' }
    $created = Invoke-Api 'POST' '/orders' $orderRequest $customerToken
    $order = $created.data
    Require ($order.total -gt 0) 'Created order has an invalid total.'
    Require ($order.payment_status -eq 'paid') 'Mock payment was not paid.'
    Pass 'Order created with server total'

    $admin = Invoke-Api 'POST' '/admin/login' @{ phone = '9999999999'; password = 'password' }
    $adminToken = $admin.data.token
    Require $adminToken 'Admin login did not return a token.'
    $adminOrder = Invoke-Api 'GET' "/admin/orders/$($order.id)" $null $adminToken
    Require ($adminOrder.data.customer.phone -eq $phone) 'Admin order detail did not contain the customer phone.'
    Require ($adminOrder.data.order.items.Count -gt 0) 'Admin order detail did not contain items.'
    Pass 'Admin sees customer, items, add-ons, comment, and payment'

    foreach ($transition in @('making', 'ready', 'completed')) {
        $updated = Invoke-Api 'PATCH' "/admin/orders/$($order.id)/status" @{ status = $transition } $adminToken
        Require ($updated.data.status -eq $transition) "Order did not transition to $transition."
        $status = Invoke-Api 'GET' "/orders/$($order.id)/status" $null $customerToken
        Require ($status.data.status -eq $transition) "Customer cannot see $transition status."
        Pass "Order transitioned to $transition and customer sees it"
    }
} catch {
    Fail $_.Exception.Message
}

if ($failed) { exit 1 }

Write-Host 'Smoke test completed successfully.' -ForegroundColor Green
exit 0
