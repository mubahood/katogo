<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'Katogo') }} - My Account</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            <img class="block h-8 w-auto" src="/logo.png" alt="Katogo">
                        </div>
                        
                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="/" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Home
                            </a>
                            <a href="/movies" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                Movies
                            </a>
                        </div>
                    </div>
                    
                    <!-- Settings Dropdown -->
                    <div class="hidden sm:flex sm:items-center sm:ml-6">
                        @auth
                        <div class="ml-3 relative">
                            <div>
                                <button 
                                    id="account-menu-trigger"
                                    type="button" 
                                    class="bg-white flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                                    aria-expanded="false" 
                                    aria-haspopup="true"
                                    onclick="toggleAccountDropdown()"
                                >
                                    <span class="sr-only">Open user menu</span>
                                    <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&color=7F9CF5&background=EBF4FF" alt="">
                                </button>
                            </div>
                            
                            <!-- Account dropdown menu -->
                            <div id="account-dropdown" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="account-menu-trigger">
                                <a href="#" onclick="window.showAccountLayout('profile')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                                <a href="#" onclick="window.showAccountLayout('dashboard')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Dashboard</a>
                                <a href="#" onclick="window.showAccountLayout('watchlist')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Watchlist</a>
                                <form method="POST" action="{{ route('logout') }}" class="block">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</button>
                                </form>
                            </div>
                        </div>
                        @else
                        <div class="ml-3">
                            <a href="{{ route('login') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                                Login
                            </a>
                        </div>
                        @endauth
                        
                        <!-- Account Quick Links -->
                        <div class="ml-4 flex items-center space-x-4">
                            <button 
                                onclick="window.showAccountLayout('dashboard')" 
                                class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
                            >
                                Dashboard
                            </button>
                            <button 
                                onclick="window.showAccountLayout('watchlist')" 
                                class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
                            >
                                Watchlist
                            </button>
                            <button 
                                onclick="window.showAccountLayout('profile')" 
                                class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium"
                            >
                                Profile
                            </button>
                        </div>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <div class="-mr-2 flex items-center sm:hidden">
                        <button 
                            onclick="window.showAccountLayout('dashboard')"
                            type="button" 
                            class="bg-white inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                            aria-controls="mobile-menu" 
                            aria-expanded="false"
                        >
                            <span class="sr-only">Open account menu</span>
                            <!-- Account icon -->
                            <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Heading -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <h1 class="text-3xl font-bold text-gray-900">
                        My Account
                    </h1>
                    
                    <!-- Quick Access Buttons -->
                    <div class="flex space-x-3">
                        <button 
                            onclick="window.showAccountLayout('watchlist')"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            📺 Watchlist
                        </button>
                        <button 
                            onclick="window.showAccountLayout('history')"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            🕒 History
                        </button>
                        <button 
                            onclick="window.showAccountLayout('likes')"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            ❤️ Likes
                        </button>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main content -->
        <main>
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <!-- Replace this with your content -->
                <div class="px-4 py-6 sm:px-0">
                    <div class="border-4 border-dashed border-gray-200 rounded-lg h-96 flex items-center justify-center">
                        <div class="text-center">
                            <h2 class="text-2xl font-bold text-gray-900 mb-4">Welcome to Your Account</h2>
                            <p class="text-gray-600 mb-6">Click any of the buttons above to access your account features.</p>
                            
                            <!-- Account Layout Container will be dynamically inserted here -->
                            <div class="space-y-4">
                                <button 
                                    onclick="window.showAccountLayout('dashboard')"
                                    class="w-full inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    📊 Open Dashboard
                                </button>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <button 
                                        onclick="window.showAccountLayout('profile')"
                                        class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        👤 Profile
                                    </button>
                                    <button 
                                        onclick="window.showAccountLayout('chats')"
                                        class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        💬 Messages
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Account Layout will be dynamically mounted here -->
    <div id="account-layout-root"></div>
    
    <!-- Auth Token Script (if user is authenticated) -->
    @auth
    <script>
        // Set auth token for API calls
        const authToken = '{{ auth()->user()->createToken("web-session")->plainTextToken ?? "" }}';
        if (authToken) {
            localStorage.setItem('auth_token', authToken);
        }
        
        // User data for frontend
        window.currentUser = @json(auth()->user());
    </script>
    @endauth
    
    <script>
        // Account dropdown toggle
        function toggleAccountDropdown() {
            const dropdown = document.getElementById('account-dropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('account-dropdown');
            const trigger = document.getElementById('account-menu-trigger');
            
            if (dropdown && trigger && !trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>