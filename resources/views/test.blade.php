<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test</title>
</head>
<body>
    <script>
        // Create a function to send a request with cookies
        async function sendRequestWithCookies() {
            const url = 'https://ugaflix.com/shows/squid-game-season-2-vj-junior/eps-1/6945';

            // Define the cookies
            const cookies = [
                'laravel_session=eyJpdiI6Im5Cd3cxR2NEYXUxemNSRXNZVEFzc3c9PSIsInZhbHVlIjoiMjNPWHRSYVR4bkZoM1VrQzZlWk9FOEVCYkE1SEtVSEVkR0gwRXRTdHVhUjl3aDU2L2t5dGFsQktzOUQybVU3NytXRzdpZ3V6d05DMmV3Vkk3ZFZVWHB6VXFHKzVqdCtkNk14RDZKd1JrZkdTL1RzRmY4Ny9yenRBTjcxYTNDZVgiLCJtYWMiOiJjNzdmYzU4MjNiNjdmZDgwZWE1NTZjZGFjYzE4M2YyM2M1MTEwMThlMDAxNDBhNGFiNjliZDgwYWJmZDY1NzU3IiwidGFnIjoiIn0%3D; Path=/; Secure; HttpOnly; Expires=Tue, 15 Apr 2025 02:12:51 GMT;',
                'XSRF-TOKEN=eyJpdiI6ImZ5U09JNE9PK0NTeUc0WkVDbmNsVGc9PSIsInZhbHVlIjoiSXlaU3h0RkMwbUd6R3dITHE0TDd4d2JWcVFXTi9zdzJaM1ovVTMyQzhLN0czUzB0MUtVNlJlZ3B2VlRhS1UyajludVFWNVlocTBQUUkzSXZwZUp5c3U4clhWOEdDdFpSejF6bFFzQnNpcXdHNVRKWjlmOXZabk9tYmh6dEVCZ1kiLCJtYWMiOiJiNzU4ZDAxOTJhYjg1N2I4ZGI1MDMyZDBlYjgxYzQ2ZDU3MDdiOTA0ZGY0NGY0ZjcwNDliMjQ1ZGViOTg2NjNhIiwidGFnIjoiIn0%3D; Path=/; Secure; Expires=Tue, 15 Apr 2025 02:12:51 GMT;'
            ];

            // Send the request using fetch
            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cookie': cookies.join(' ')
                    }
                });

                const data = await response.text();
                console.log('Response:', data);
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Call the function
        sendRequestWithCookies();
    </script>
</body>
</html>