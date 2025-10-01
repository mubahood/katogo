# KATOGO PROJECT - Comprehensive Technical Documentation

## 📋 Table of Contents
1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Technology Stack](#technology-stack)
4. [Database Schema](#database-schema)
5. [Backend API Architecture](#backend-api-architecture)
6. [Frontend Architecture](#frontend-architecture)
7. [Mobile Application](#mobile-application)
8. [Authentication & Security](#authentication--security)
9. [Chat System](#chat-system)
10. [E-commerce Features](#e-commerce-features)
11. [Media Management](#media-management)
12. [Admin Panel](#admin-panel)
13. [Deployment & Infrastructure](#deployment--infrastructure)
14. [API Documentation](#api-documentation)
15. [Development Workflow](#development-workflow)

---

## 🎯 Project Overview

**Katogo** is a comprehensive multi-platform application ecosystem that combines:
- **E-commerce marketplace** for product buying/selling
- **Video streaming platform** for movies and content
- **Social features** including chat system and user interactions
- **Mobile applications** (Flutter-based)
- **Web application** (React/TypeScript)
- **Admin management panel** (Laravel Admin)

### Core Features
- 🛒 **E-commerce**: Product listings, shopping cart, checkout, payments
- 🎬 **Media Streaming**: Movie management, video playback, content distribution
- 💬 **Communication**: Real-time chat system between buyers/sellers
- 👥 **User Management**: Authentication, profiles, subscriptions
- 📱 **Cross-Platform**: Web (React), Mobile (Flutter), Admin (Laravel)
- 🔒 **Security**: JWT authentication, content moderation

---

## 🏗️ System Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Client    │    │  Mobile Client  │    │  Admin Panel    │
│   (React TS)    │    │   (Flutter)     │    │ (Laravel Admin) │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          └──────────────────────┼──────────────────────┘
                                 │
          ┌─────────────────────────────────────────────┐
          │            API Gateway / Load Balancer      │
          └─────────────────────┬───────────────────────┘
                                │
          ┌─────────────────────────────────────────────┐
          │         Laravel Backend (PHP 8.1+)         │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │ API Routes  │  │Admin Routes │          │
          │  └─────────────┘  └─────────────┘          │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │Controllers  │  │Middleware   │          │
          │  └─────────────┘  └─────────────┘          │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │   Models    │  │ Services    │          │
          │  └─────────────┘  └─────────────┘          │
          └─────────────────────┬───────────────────────┘
                                │
          ┌─────────────────────────────────────────────┐
          │              MySQL Database                 │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │    Users    │  │  Products   │          │
          │  └─────────────┘  └─────────────┘          │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │ Chat System │  │   Movies    │          │
          │  └─────────────┘  └─────────────┘          │
          │  ┌─────────────┐  ┌─────────────┐          │
          │  │   Orders    │  │  Companies  │          │
          │  └─────────────┘  └─────────────┘          │
          └─────────────────────────────────────────────┘
```

---

## 🔧 Technology Stack

### Backend
- **Framework**: Laravel 10+ (PHP 8.1+)
- **Database**: MySQL
- **Authentication**: JWT (tymon/jwt-auth)
- **Admin Panel**: Laravel Admin (encore/laravel-admin)
- **API Architecture**: RESTful APIs
- **File Storage**: Local storage with cloud integration options
- **Queue System**: Laravel Queues (for background jobs)

### Frontend Web
- **Framework**: React 18+ with TypeScript
- **Build Tool**: Vite
- **UI Library**: React Bootstrap
- **State Management**: Redux Toolkit (RTK Query)
- **Routing**: React Router v6
- **HTTP Client**: Axios
- **Styling**: CSS Modules + Bootstrap 5

### Mobile Application
- **Framework**: Flutter
- **Language**: Dart
- **Local Storage**: SQLite
- **HTTP Client**: Dio/http
- **State Management**: Provider/GetX
- **Platform**: Android & iOS

### Development Tools
- **Version Control**: Git
- **Dependency Management**: Composer (PHP), npm (Node.js), pub (Dart)
- **Code Quality**: PHP CS Fixer, ESLint, Prettier
- **Testing**: PHPUnit (Backend), Jest (Frontend), Flutter Test (Mobile)

---

## 🗄️ Database Schema

### Core Tables

#### Users Table
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    avatar TEXT NULL,
    phone_number VARCHAR(50) NULL,
    address TEXT NULL,
    status VARCHAR(50) DEFAULT 'active',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Products/Stock Items Table
```sql
CREATE TABLE stock_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    created_by_id BIGINT NOT NULL,
    stock_category_id BIGINT NOT NULL,
    stock_sub_category_id BIGINT NOT NULL,
    financial_period_id BIGINT NOT NULL,
    name TEXT NOT NULL,
    description TEXT NULL,
    image TEXT NULL,
    barcode VARCHAR(255) NULL,
    sku VARCHAR(255) NULL,
    current_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 0,
    buying_price DECIMAL(15,2) DEFAULT 0,
    selling_price DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (created_by_id) REFERENCES users(id),
    FOREIGN KEY (stock_category_id) REFERENCES stock_categories(id),
    FOREIGN KEY (stock_sub_category_id) REFERENCES stock_sub_categories(id)
);
```

#### Movies Table
```sql
CREATE TABLE movie_models (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title TEXT NULL,
    external_url TEXT NULL,
    url TEXT NULL,
    image_url TEXT NULL,
    thumbnail_url TEXT NULL,
    description TEXT NULL,
    year VARCHAR(10) NULL,
    rating VARCHAR(10) NULL,
    duration INT NULL,
    size FLOAT NULL,
    genre TEXT NULL,
    director TEXT NULL,
    stars TEXT NULL,
    country TEXT NULL,
    language TEXT NULL,
    views_time_count INT DEFAULT 0,
    is_trending VARCHAR(10) DEFAULT 'No',
    trending_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Chat System Tables
```sql
-- Chat Heads (Conversation metadata)
CREATE TABLE chat_heads (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NULL,
    product_name VARCHAR(255) NULL,
    product_photo TEXT NULL,
    product_owner_id BIGINT NOT NULL,
    product_owner_name VARCHAR(255) NULL,
    product_owner_photo TEXT NULL,
    customer_id BIGINT NOT NULL,
    customer_name VARCHAR(255) NULL,
    customer_photo TEXT NULL,
    last_message_body TEXT NULL,
    last_message_time DATETIME NULL,
    last_message_status VARCHAR(50) DEFAULT 'new',
    type VARCHAR(50) DEFAULT 'product',
    sender_unread_count INT DEFAULT 0,
    receiver_unread_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_owner_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES users(id)
);

-- Chat Messages
CREATE TABLE chat_messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    chat_head_id BIGINT NOT NULL,
    sender_id BIGINT NOT NULL,
    receiver_id BIGINT NOT NULL,
    sender_name VARCHAR(255) NULL,
    sender_photo TEXT NULL,
    receiver_name VARCHAR(255) NULL,
    receiver_photo TEXT NULL,
    body TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'text',
    status VARCHAR(50) DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_head_id) REFERENCES chat_heads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);
```

#### Companies & Financial Management
```sql
CREATE TABLE companies (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    owner_id BIGINT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NULL,
    logo TEXT NULL,
    website TEXT NULL,
    about TEXT NULL,
    status VARCHAR(50) DEFAULT 'active',
    license_expire DATE NULL,
    address TEXT NULL,
    phone_number VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE financial_periods (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Active',
    description TEXT NULL,
    total_investment BIGINT DEFAULT 0,
    total_sales BIGINT DEFAULT 0,
    total_profit BIGINT DEFAULT 0,
    total_expenses BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);
```

---

## 🔗 Backend API Architecture

### API Structure
```
/api
├── auth/
│   ├── login (POST)
│   ├── register (POST)
│   ├── password-reset (POST)
│   └── request-password-reset-code (POST)
├── user/
│   ├── me (GET)
│   ├── disable-account (POST)
│   └── manifest (GET)
├── products/
│   ├── api/products (GET) - List products
│   ├── api/products (POST) - Create product
│   ├── products-1 (GET) - Enhanced product listing
│   ├── product-create (POST) - Create with media
│   └── products-delete (POST) - Delete products
├── chat/
│   ├── chat-heads (GET) - Get user conversations
│   ├── chat-messages (GET) - Get messages for chat
│   ├── chat-start (POST) - Start new conversation
│   ├── chat-send (POST) - Send message
│   ├── chat-delete (POST) - Delete conversation
│   └── chat-mark-as-read (POST) - Mark messages as read
├── movies/
│   ├── movies (GET) - List movies
│   ├── movie/{id} (GET) - Get single movie
│   ├── random-movie (GET) - Get random movie
│   └── video-progress (POST/GET) - Track viewing progress
├── media/
│   ├── file-uploading (POST) - Generic file upload
│   └── post-media-upload (POST) - Media with metadata
└── moderation/
    └── filter-content (POST) - Content moderation
```

### API Response Format
```php
// Success Response
{
    "code": 1,
    "message": "Success",
    "data": {
        // Response data
    }
}

// Error Response
{
    "code": 0,
    "message": "Error message",
    "data": null
}
```

### Key Controllers

#### ApiController.php
**Primary API controller handling most endpoints**

```php
class ApiController extends Controller
{
    // Authentication endpoints
    public function login(Request $request);
    public function register(Request $request);
    public function me(Request $request);
    
    // Product management
    public function product_create(Request $request);
    public function products_1(Request $request);
    public function products_delete(Request $request);
    
    // Chat system
    public function chat_heads(Request $request);
    public function chat_messages(Request $request);
    public function chat_start(Request $request);
    public function chat_send(Request $request);
    
    // File handling
    public function file_uploading(Request $request);
    public function upload_media(Request $request);
    
    // Utility methods
    protected function success($data, $message = "Success");
    protected function error($message = "Error");
}
```

#### DynamicCrudController.php
**Handles movie-related operations**

```php
class DynamicCrudController extends Controller
{
    public function movies(Request $request);
    public function movie($id);
    public function random_movie();
    public function save_video_progress(Request $request);
    public function get_video_progress($movie_id);
    public function get_watch_history(Request $request);
}
```

#### ModerationController.php
**Content filtering and moderation**

```php
class ModerationController extends Controller
{
    public function filterContent(Request $request);
    // AI-powered content moderation
    // Automated filtering for inappropriate content
}
```

### Middleware & Security

#### JWT Middleware
```php
class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
        } catch (Exception $e) {
            return response()->json(['error' => 'Token invalid'], 401);
        }
        return $next($request);
    }
}
```

#### Rate Limiting
```php
// Applied to video progress tracking
Route::post('video-progress', [DynamicCrudController::class, 'save_video_progress'])
    ->middleware('throttle:video-progress');
```

---

## 🎨 Frontend Architecture

### React Application Structure
```
src/
├── app/
│   ├── components/           # Reusable UI components
│   │   ├── Layout/          # Layout components
│   │   ├── Auth/            # Authentication components
│   │   ├── Products/        # Product-related components
│   │   ├── Movies/          # Movie components
│   │   ├── Chat/            # Chat interface
│   │   └── Account/         # User account components
│   ├── pages/               # Page components
│   │   ├── auth/           # Authentication pages
│   │   ├── account/        # Account management
│   │   ├── ProductDetailPage/ # Product details
│   │   └── Chat/           # Chat pages
│   ├── services/           # API services
│   │   ├── ApiService.ts   # Generic API client
│   │   ├── ChatApiService.ts # Chat-specific APIs
│   │   ├── AccountApiService.ts # Account APIs
│   │   └── realProductsApi.ts # RTK Query API
│   ├── store/              # Redux store
│   │   ├── slices/         # Redux slices
│   │   └── store.ts        # Store configuration
│   ├── types/              # TypeScript definitions
│   ├── routing/            # Route configuration
│   └── styles/             # CSS/styling files
└── resources/              # Laravel resources
    ├── js/                 # JS components
    └── views/              # Blade templates
```

### Key Frontend Components

#### Chat System Components
```typescript
// ChatPage.tsx - Main chat interface
interface ChatPageProps {
  // No props needed - uses URL parameters
}

interface ChatHead {
  id: number;
  customer_id: string;
  customer_name: string;
  customer_photo: string;
  product_owner_id: string;
  product_owner_name: string;
  product_owner_photo: string;
  last_message_body: string;
  last_message_time: string;
  last_message_status: string;
}

interface ChatMessage {
  id: number;
  chat_head_id: number;
  sender_id: number;
  receiver_id: number;
  body: string;
  created_at: string;
  status: string;
}
```

#### Product Components
```typescript
// ContactSellerButton.tsx - Initiates chat with seller
interface ContactSellerButtonProps {
  productId: number;
  sellerId: number;
  sellerName: string;
  productName: string;
  className?: string;
}

// ProductCard.tsx - Product display component
interface ProductCardProps {
  product: Product;
  onAddToCart?: (product: Product) => void;
  showQuickView?: boolean;
}
```

#### API Services
```typescript
// ChatApiService.ts
export class ChatApiService {
  static async getChatHeads(): Promise<ChatHead[]>;
  static async getChatMessages(chatHeadId: number): Promise<ChatMessage[]>;
  static async startChat(data: StartChatRequest): Promise<ChatHead>;
  static async sendMessage(data: SendMessageRequest): Promise<ChatMessage>;
  static async markAsRead(chatHeadId: number): Promise<void>;
}

// AccountApiService.ts
export class AccountApiService {
  static async getChatHeads(): Promise<ChatHead[]>;
  static async getChatMessages(chatHeadId: number): Promise<ChatMessage[]>;
  static async sendChatMessage(data: SendMessageData): Promise<ChatMessage>;
}
```

### State Management with Redux
```typescript
// authSlice.ts
interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  loading: boolean;
  error: string | null;
}

// Real-time API with RTK Query
export const realProductsApi = createApi({
  reducerPath: 'realProductsApi',
  baseQuery: fetchBaseQuery({
    baseUrl: '/api/',
    prepareHeaders: (headers, { getState }) => {
      const token = (getState() as RootState).auth.token;
      if (token) {
        headers.set('authorization', `Bearer ${token}`);
      }
      return headers;
    },
  }),
  tagTypes: ['Product', 'Category', 'Chat'],
  endpoints: (builder) => ({
    getProducts: builder.query<ProductsResponse, ProductsQueryParams>({
      query: (params) => ({ url: 'products', params }),
      providesTags: ['Product'],
    }),
    // ... other endpoints
  }),
});
```

---

## 📱 Mobile Application

### Flutter App Structure
```
lib/
├── controllers/           # State management (GetX)
│   └── MainController.dart
├── models/               # Data models
│   ├── ChatHead.dart
│   ├── ChatMessage.dart
│   ├── Product.dart
│   └── UserModel.dart
├── screens/             # UI screens
│   ├── shop/           # E-commerce screens
│   │   ├── chat/       # Chat interfaces
│   │   └── products/   # Product screens
│   └── auth/           # Authentication screens
├── utils/              # Utilities
│   ├── Utils.dart      # Helper functions
│   └── CustomTheme.dart # Theme configuration
└── services/           # API services
```

### Key Mobile Components

#### Chat System (Flutter)
```dart
// ChatHead.dart - Conversation model
class ChatHead {
  int id = 0;
  String product_id = "";
  String product_owner_id = "";
  String product_owner_name = "";
  String product_owner_photo = "";
  String customer_id = "";
  String customer_name = "";
  String customer_photo = "";
  String last_message_body = "";
  String last_message_time = "";
  String last_message_status = "";
  
  static fromJson(dynamic m) {
    // Parse JSON response from API
  }
  
  int myUnread(LoggedInUserModel u) {
    // Calculate unread messages for current user
  }
}

// ChatsScreen.dart - Chat list interface
class ChatsScreen extends StatefulWidget {
  // Displays list of conversations
  // Implements real-time polling
  // Handles search and filtering
}

// ChatScreen.dart - Individual chat interface
class ChatScreen extends StatefulWidget {
  // Displays messages for specific conversation
  // Handles message sending/receiving
  // Real-time message updates
}
```

#### Product Models (Flutter)
```dart
// Product.dart
class Product {
  int id = 0;
  String name = "";
  String description = "";
  String feature_photo = "";
  String price_1 = "";
  String price_2 = "";
  String category_id = "";
  String user = "";
  
  static fromJson(dynamic m) {
    // Parse product data
  }
}
```

#### API Integration (Flutter)
```dart
// Utils.dart - HTTP client
class Utils {
  static Future<dynamic> http_post(String url, Map<String, dynamic> data) async {
    // Handle POST requests with authentication
  }
  
  static Future<dynamic> http_get(String url) async {
    // Handle GET requests
  }
  
  static String getImageUrl(String imagePath) {
    // Construct full image URLs
  }
}
```

### Mobile App Features
- **Offline Support**: Local SQLite storage for chat history
- **Real-time Updates**: Polling-based chat updates
- **Image Handling**: Cached network images with fallbacks
- **Authentication**: JWT token management
- **Push Notifications**: Integration ready
- **Cross-platform**: Single codebase for Android/iOS

---

## 🔐 Authentication & Security

### JWT Authentication Flow
```php
// Login Process
1. User submits credentials
2. Server validates credentials
3. JWT token generated with user payload
4. Token returned to client
5. Client stores token (localStorage/SecureStorage)
6. Token included in subsequent API requests

// Token Structure
{
  "sub": "user_id",
  "iat": "issued_at",
  "exp": "expires_at",
  "user": {
    "id": 123,
    "name": "User Name",
    "email": "user@example.com"
  }
}
```

### Security Middleware
```php
// JwtMiddleware.php
class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }
        
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $user = User::find($payload->get('sub'));
            
            if (!$user || $user->status !== 'active') {
                return response()->json(['error' => 'Invalid user'], 401);
            }
            
            $request->merge(['user' => $user]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        return $next($request);
    }
}
```

### Content Moderation
```php
// ModerationController.php
class ModerationController extends Controller
{
    public function filterContent(Request $request)
    {
        $content = $request->input('content');
        
        // AI-based content filtering
        $moderationResult = $this->checkContent($content);
        
        return response()->json([
            'safe' => $moderationResult['safe'],
            'confidence' => $moderationResult['confidence'],
            'categories' => $moderationResult['flagged_categories']
        ]);
    }
    
    private function checkContent($content)
    {
        // Implement content moderation logic
        // Integration with external AI services
        // Custom keyword filtering
        // Image content analysis
    }
}
```

---

## 💬 Chat System

### Real-time Chat Architecture
```
User A (Web/Mobile) ←→ API Server ←→ Database ←→ API Server ←→ User B (Web/Mobile)
                      ↕                       ↕
                 Chat Messages            Chat Heads
```

### Chat System Components

#### 1. Chat Heads Management
```php
// ApiController.php - chat_heads method
public function chat_heads(Request $request)
{
    $user = Utils::get_user($request);
    
    // Get conversations where user is participant
    $chat_heads = ChatHead::where('product_owner_id', $user->id)
                         ->orWhere('customer_id', $user->id)
                         ->orderBy('updated_at', 'desc')
                         ->get();
    
    foreach ($chat_heads as $head) {
        // Determine other participant
        $other_user_id = ($user->id == $head->customer_id) 
                        ? $head->product_owner_id 
                        : $head->customer_id;
        
        $other_user = User::find($other_user_id);
        
        // Set participant information
        if ($user->id == $head->customer_id) {
            $head->product_owner_name = $other_user->name;
            $head->product_owner_photo = $other_user->avatar;
        } else {
            $head->customer_name = $other_user->name;
            $head->customer_photo = $other_user->avatar;
        }
        
        // Get last message
        $last_message = ChatMessage::where('chat_head_id', $head->id)
                                  ->orderBy('created_at', 'desc')
                                  ->first();
        
        if ($last_message) {
            $head->last_message_body = $last_message->body;
            $head->last_message_time = $last_message->created_at;
        }
    }
    
    return $this->success($chat_heads);
}
```

#### 2. Message Management
```php
// Chat message sending
public function chat_send(Request $request)
{
    $sender = Utils::get_user($request);
    $receiver = User::find($request->receiver_id);
    $chat_head = ChatHead::find($request->chat_head_id);
    
    $message = new ChatMessage();
    $message->chat_head_id = $chat_head->id;
    $message->sender_id = $sender->id;
    $message->receiver_id = $receiver->id;
    $message->body = $request->body;
    $message->type = $request->type ?? 'text';
    $message->status = 'sent';
    $message->save();
    
    // Update chat head with latest message
    $chat_head->last_message_body = $message->body;
    $chat_head->last_message_time = now();
    $chat_head->save();
    
    return $this->success($message);
}
```

#### 3. Frontend Chat Interface
```typescript
// ChatPage.tsx
export const ChatPage: React.FC = () => {
  const [chatHeads, setChatHeads] = useState<ChatHead[]>([]);
  const [selectedChat, setSelectedChat] = useState<ChatHead | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [messageText, setMessageText] = useState('');
  
  // Load chat heads on component mount
  useEffect(() => {
    loadChatHeads();
  }, []);
  
  // Auto-select chat from URL parameter
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const chatHeadId = params.get('chatHeadId');
    
    if (chatHeadId && chatHeads.length > 0) {
      const targetChat = chatHeads.find(chat => 
        chat.id === parseInt(chatHeadId)
      );
      if (targetChat) {
        selectChat(targetChat);
      }
    }
  }, [chatHeads]);
  
  const loadChatHeads = async () => {
    const heads = await ChatApiService.getChatHeads();
    setChatHeads(heads);
  };
  
  const selectChat = async (chat: ChatHead) => {
    setSelectedChat(chat);
    const msgs = await ChatApiService.getChatMessages(chat.id);
    setMessages(msgs);
  };
  
  const sendMessage = async () => {
    if (!selectedChat || !messageText.trim()) return;
    
    const message = await ChatApiService.sendMessage({
      chat_head_id: selectedChat.id,
      receiver_id: getOtherParticipantId(selectedChat),
      body: messageText,
      type: 'text'
    });
    
    setMessages(prev => [...prev, message]);
    setMessageText('');
  };
};
```

#### 4. Mobile Chat Implementation
```dart
// ChatScreen.dart (Flutter)
class ChatScreen extends StatefulWidget {
  @override
  _ChatScreenState createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  ChatHead chatHead = ChatHead();
  List<ChatMessage> _msgs = [];
  TextEditingController _txtC = TextEditingController();
  
  @override
  void initState() {
    super.initState();
    _initialize();
  }
  
  Future<void> _initialize() async {
    // Load chat head and messages
    await _loadMessages();
    _pollLoop(); // Start real-time polling
  }
  
  Future<void> _loadMessages() async {
    _msgs = await ChatMessage.get_items(
      _mainC.userModel,
      where: 'chat_head_id = ${chatHead.id}',
    );
    setState(() {});
  }
  
  void _pollLoop() {
    Timer.periodic(Duration(seconds: 3), (timer) {
      if (mounted) {
        _loadMessages();
      } else {
        timer.cancel();
      }
    });
  }
  
  Future<void> _sendMessage() async {
    if (_txtC.text.trim().isEmpty) return;
    
    final response = await Utils.http_post('chat-send', {
      'chat_head_id': chatHead.id,
      'receiver_id': receiver_id,
      'body': _txtC.text,
      'type': 'text',
    });
    
    if (response['code'] == 1) {
      _txtC.clear();
      await _loadMessages();
    }
  }
}
```

### Chat Features
- **Real-time Messaging**: Polling-based updates (3-second intervals)
- **Unread Message Tracking**: Per-conversation unread counts
- **Message Types**: Text, images, files (extensible)
- **Participant Management**: Automatic user role detection
- **Message Status**: Sent, delivered, read tracking
- **Chat History**: Persistent message storage
- **Mobile Offline**: Local SQLite caching

---

## 🛒 E-commerce Features

### Product Management
```php
// Product creation with media
public function product_create(Request $request)
{
    $user = Utils::get_user($request);
    
    $product = new StockItem();
    $product->company_id = $user->company_id;
    $product->created_by_id = $user->id;
    $product->name = $request->name;
    $product->description = $request->description;
    $product->buying_price = $request->buying_price;
    $product->selling_price = $request->selling_price;
    $product->current_quantity = $request->quantity;
    
    // Handle image upload
    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $imagePath = $image->store('products', 'public');
        $product->image = $imagePath;
    }
    
    $product->save();
    
    return $this->success($product, 'Product created successfully');
}
```

### Shopping Cart (Frontend)
```typescript
// CartService.ts
export class CartService {
  private static CART_KEY = 'ugflix_cart';
  
  static addToCart(productId: number, quantity: number = 1): void {
    const cart = this.getCart();
    const existingItem = cart.items.find(item => item.productId === productId);
    
    if (existingItem) {
      existingItem.quantity += quantity;
    } else {
      cart.items.push({
        productId,
        quantity,
        addedAt: new Date().toISOString()
      });
    }
    
    this.saveCart(cart);
    this.notifyCartUpdate();
  }
  
  static getCartTotal(): number {
    const cart = this.getCart();
    return cart.items.reduce((total, item) => {
      const product = this.getProductById(item.productId);
      return total + (product ? product.price * item.quantity : 0);
    }, 0);
  }
  
  static getCartItemCount(): number {
    const cart = this.getCart();
    return cart.items.reduce((total, item) => total + item.quantity, 0);
  }
}
```

### Order Management
```typescript
// Order processing flow
interface Order {
  id: number;
  user_id: number;
  total_amount: number;
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  items: OrderItem[];
  shipping_address: Address;
  payment_method: string;
  created_at: string;
}

interface OrderItem {
  product_id: number;
  quantity: number;
  price: number;
  total: number;
}
```

---

## 🎬 Media Management

### Movie System
```php
// Movie model with comprehensive metadata
class MovieModel extends Model
{
    protected $fillable = [
        'title', 'external_url', 'url', 'image_url', 'thumbnail_url',
        'description', 'year', 'rating', 'duration', 'size', 'genre',
        'director', 'stars', 'country', 'language', 'views_time_count',
        'is_trending', 'trending_time'
    ];
    
    // Video URL validation and testing
    public function testVideoUrl(): bool
    {
        $this->video_url_tested_by_curl = 'Yes';
        $works = $this->performCurlTest($this->url);
        $this->video_url_tested_by_curl_works = $works ? 'Yes' : 'No';
        $this->save();
        
        return $works;
    }
    
    // Firebase integration for video hosting
    public function transferToFirebase(): bool
    {
        $this->firebase_transfer_attempted = 'Yes';
        
        try {
            $firebaseUrl = $this->uploadToFirebase($this->url);
            $this->old_video_url = $this->url;
            $this->url = $firebaseUrl;
            $this->firebase_transfer_successful = 'Yes';
            $this->save();
            
            return true;
        } catch (Exception $e) {
            $this->firebase_transfer_successful = 'No';
            $this->firebase_transfer_error = $e->getMessage();
            $this->save();
            
            return false;
        }
    }
}
```

### Video Progress Tracking
```php
// DynamicCrudController.php
public function save_video_progress(Request $request)
{
    $user = auth()->user();
    $movieId = $request->movie_id;
    $progress = $request->progress; // seconds watched
    $duration = $request->duration; // total duration
    
    $progressRecord = VideoProgress::updateOrCreate(
        [
            'user_id' => $user->id,
            'movie_id' => $movieId
        ],
        [
            'progress_seconds' => $progress,
            'total_duration' => $duration,
            'percentage' => ($progress / $duration) * 100,
            'last_watched_at' => now()
        ]
    );
    
    return response()->json(['success' => true]);
}
```

### Content Delivery
- **Video Storage**: Local storage with Firebase integration
- **Image Optimization**: Multiple resolution support
- **CDN Integration**: Ready for content delivery networks
- **Streaming Support**: Progressive video loading
- **Offline Viewing**: Download capability for mobile

---

## 🎛️ Admin Panel

### Laravel Admin Integration
```php
// Admin configuration
// config/admin.php
return [
    'name' => 'Katogo Admin',
    'logo' => '/images/logo.png',
    'database' => [
        'users_table' => 'admin_users',
        'roles_table' => 'admin_roles',
        'permissions_table' => 'admin_permissions',
    ],
    'route' => [
        'prefix' => 'admin',
        'namespace' => 'App\\Admin\\Controllers',
        'middleware' => ['web', 'admin'],
    ],
];
```

### Admin Controllers
```php
// app/Admin/Controllers/ProductController.php
class ProductController extends AdminController
{
    protected $title = 'Products';
    
    protected function grid()
    {
        $grid = new Grid(new StockItem());
        
        $grid->column('id', __('ID'));
        $grid->column('name', __('Name'));
        $grid->column('selling_price', __('Price'))->display(function ($price) {
            return 'UGX ' . number_format($price);
        });
        $grid->column('current_quantity', __('Stock'));
        $grid->column('created_at', __('Created'));
        
        $grid->filter(function($filter) {
            $filter->like('name', 'Name');
            $filter->between('selling_price', 'Price');
            $filter->between('created_at', 'Created')->datetime();
        });
        
        return $grid;
    }
    
    protected function form()
    {
        $form = new Form(new StockItem());
        
        $form->text('name', __('Name'))->required();
        $form->textarea('description', __('Description'));
        $form->currency('buying_price', __('Buying Price'));
        $form->currency('selling_price', __('Selling Price'));
        $form->number('current_quantity', __('Quantity'));
        $form->image('image', __('Image'));
        
        return $form;
    }
}
```

### Admin Features
- **User Management**: User accounts, roles, permissions
- **Product Management**: CRUD operations with media upload
- **Order Management**: Order tracking and fulfillment
- **Movie Management**: Content upload and metadata
- **Analytics Dashboard**: Sales, views, user metrics
- **Content Moderation**: Review and approve user content
- **System Settings**: Configuration management

---

## 🚀 Deployment & Infrastructure

### Server Requirements
```yaml
# Production Environment
PHP: >= 8.1
MySQL: >= 8.0
Node.js: >= 18.0
Web Server: Apache/Nginx
SSL: Required for production
Storage: 100GB+ recommended

# PHP Extensions
- php-mysql
- php-gd
- php-curl
- php-json
- php-mbstring
- php-xml
- php-zip
```

### Environment Configuration
```env
# .env file
APP_NAME=Katogo
APP_ENV=production
APP_KEY=base64:generated-key
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=katogo_prod
DB_USERNAME=katogo_user
DB_PASSWORD=secure-password

JWT_SECRET=jwt-secret-key
JWT_TTL=60

FILESYSTEM_DRIVER=local
# For production, consider S3 or similar
# FILESYSTEM_DRIVER=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

# Firebase Configuration (for video storage)
FIREBASE_CREDENTIALS=path/to/credentials.json
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_STORAGE_BUCKET=your-bucket

# OneSignal (for push notifications)
ONESIGNAL_APP_ID=your-app-id
ONESIGNAL_REST_API_KEY=your-api-key
```

### Docker Deployment
```dockerfile
# Dockerfile
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]
```

---

## 📚 API Documentation

### Authentication Endpoints

#### POST /api/auth/login
```json
Request:
{
  "email": "user@example.com",
  "password": "password"
}

Response:
{
  "code": 1,
  "message": "Success",
  "data": {
    "token": "jwt-token-here",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "avatar": "path/to/avatar.jpg"
    }
  }
}
```

#### POST /api/auth/register
```json
Request:
{
  "name": "John Doe",
  "email": "user@example.com",
  "password": "password",
  "password_confirmation": "password"
}

Response:
{
  "code": 1,
  "message": "Registration successful",
  "data": {
    "user": { /* user object */ },
    "token": "jwt-token"
  }
}
```

### Product Endpoints

#### GET /api/api/products
```json
Parameters:
- page: int (default: 1)
- limit: int (default: 20)
- category_id: int (optional)
- search: string (optional)

Response:
{
  "code": 1,
  "message": "Success",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Product Name",
        "description": "Product description",
        "selling_price": 25000,
        "image": "path/to/image.jpg",
        "category": "Electronics",
        "vendor": "Vendor Name"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 10,
      "total_items": 200
    }
  }
}
```

### Chat Endpoints

#### GET /api/chat-heads
```json
Headers:
Authorization: Bearer jwt-token

Response:
{
  "code": 1,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "customer_id": 123,
      "customer_name": "John Doe",
      "customer_photo": "path/to/photo.jpg",
      "product_owner_id": 456,
      "product_owner_name": "Jane Smith",
      "product_owner_photo": "path/to/photo.jpg",
      "last_message_body": "Hello, is this still available?",
      "last_message_time": "2025-01-01 12:00:00",
      "last_message_status": "sent"
    }
  ]
}
```

#### POST /api/chat-start
```json
Request:
{
  "sender_id": 123,
  "receiver_id": 456,
  "product_id": 789
}

Response:
{
  "code": 1,
  "message": "Chat started successfully",
  "data": {
    "id": 1,
    "customer_id": 123,
    "product_owner_id": 456,
    "product_id": 789,
    /* other chat head fields */
  }
}
```

---

## 🔧 Development Workflow

### Local Development Setup

#### Backend Setup
```bash
# Clone repository
git clone https://github.com/your-repo/katogo.git
cd katogo

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret

# Setup database
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

#### Frontend Setup
```bash
# Install Node.js dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build
```

#### Mobile App Setup
```bash
# Navigate to mobile app directory (if separate)
cd mobile-app

# Get Flutter dependencies
flutter pub get

# Run on Android emulator
flutter run

# Build for production
flutter build apk --release
```

### Code Standards

#### PHP (Backend)
```php
// Follow PSR-12 coding standards
// Use meaningful variable names
// Document all public methods
// Handle exceptions properly

class ExampleController extends Controller
{
    /**
     * Create a new product
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createProduct(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
            ]);
            
            $product = Product::create($validatedData);
            
            return $this->success($product, 'Product created successfully');
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->errors());
        } catch (Exception $e) {
            return $this->error('Server error occurred');
        }
    }
}
```

#### TypeScript (Frontend)
```typescript
// Use proper TypeScript types
// Implement error boundaries
// Handle loading states
// Use meaningful component names

interface ProductCardProps {
  product: Product;
  onAddToCart: (product: Product) => void;
  className?: string;
}

const ProductCard: React.FC<ProductCardProps> = ({ 
  product, 
  onAddToCart, 
  className = '' 
}) => {
  const [isLoading, setIsLoading] = useState(false);
  
  const handleAddToCart = async () => {
    try {
      setIsLoading(true);
      await onAddToCart(product);
      ToastService.success('Product added to cart');
    } catch (error) {
      ToastService.error('Failed to add product to cart');
    } finally {
      setIsLoading(false);
    }
  };
  
  return (
    <Card className={`product-card ${className}`}>
      {/* Component content */}
    </Card>
  );
};
```

#### Dart (Mobile)
```dart
// Follow Dart style guide
// Use proper state management
// Handle errors gracefully
// Implement proper navigation

class ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback? onTap;
  
  const ProductCard({
    Key? key,
    required this.product,
    this.onTap,
  }) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        child: Column(
          children: [
            // Product image
            CachedNetworkImage(
              imageUrl: Utils.getImageUrl(product.feature_photo),
              placeholder: (context, url) => CircularProgressIndicator(),
              errorWidget: (context, url, error) => Icon(Icons.error),
            ),
            // Product details
            Text(product.name),
            Text('\$${product.price_1}'),
          ],
        ),
      ),
    );
  }
}
```

### Testing Strategy

#### Backend Testing
```php
// Feature tests for API endpoints
class ChatApiTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_can_get_chat_heads()
    {
        $user = User::factory()->create();
        $chatHead = ChatHead::factory()->create(['customer_id' => $user->id]);
        
        $response = $this->actingAs($user)
                         ->getJson('/api/chat-heads');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'code',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'customer_name',
                            'product_owner_name',
                            'last_message_body'
                        ]
                    ]
                ]);
    }
}
```

#### Frontend Testing
```typescript
// Component testing with Jest and React Testing Library
import { render, screen, fireEvent } from '@testing-library/react';
import { ProductCard } from '../ProductCard';

describe('ProductCard', () => {
  const mockProduct = {
    id: 1,
    name: 'Test Product',
    price: 25000,
    image: 'test-image.jpg'
  };
  
  test('renders product information correctly', () => {
    render(<ProductCard product={mockProduct} onAddToCart={jest.fn()} />);
    
    expect(screen.getByText('Test Product')).toBeInTheDocument();
    expect(screen.getByText('UGX 25,000')).toBeInTheDocument();
  });
  
  test('calls onAddToCart when button is clicked', () => {
    const mockAddToCart = jest.fn();
    render(<ProductCard product={mockProduct} onAddToCart={mockAddToCart} />);
    
    fireEvent.click(screen.getByText('Add to Cart'));
    expect(mockAddToCart).toHaveBeenCalledWith(mockProduct);
  });
});
```

---

## 📊 Performance Optimization

### Backend Optimization
- **Database Indexing**: Proper indexes on foreign keys and search fields
- **Query Optimization**: Use eager loading to prevent N+1 queries
- **Caching**: Redis/Memcached for frequently accessed data
- **API Rate Limiting**: Prevent abuse and ensure fair usage
- **File Storage**: CDN integration for media files

### Frontend Optimization
- **Code Splitting**: Lazy loading of components and routes
- **Image Optimization**: WebP format, multiple resolutions
- **Bundle Optimization**: Tree shaking and minimization
- **Caching Strategy**: Service workers for offline support
- **Performance Monitoring**: Real user metrics tracking

### Mobile Optimization
- **Local Caching**: SQLite for offline data access
- **Image Caching**: Network image caching for better UX
- **Background Sync**: Queue operations for offline mode
- **Memory Management**: Proper disposal of resources
- **Battery Optimization**: Efficient polling strategies

---

## 🔮 Future Enhancements

### Planned Features
1. **Real-time Chat**: WebSocket implementation for instant messaging
2. **Push Notifications**: Firebase/OneSignal integration
3. **Payment Gateway**: Mobile money and card payment integration
4. **Advanced Search**: Elasticsearch for better product discovery
5. **AI Recommendations**: Machine learning for personalized content
6. **Multi-vendor Support**: Allow multiple sellers per platform
7. **Live Streaming**: Real-time video streaming capabilities
8. **Social Features**: User reviews, ratings, and social sharing

### Scalability Considerations
- **Microservices Architecture**: Split monolith into specialized services
- **Database Sharding**: Horizontal scaling for large datasets
- **CDN Integration**: Global content delivery
- **Auto-scaling**: Cloud-based infrastructure scaling
- **Load Balancing**: Distribute traffic across multiple servers

---

## 📝 Conclusion

Katogo represents a comprehensive digital ecosystem that successfully combines e-commerce, media streaming, and social communication features. The architecture demonstrates modern development practices with:

- **Scalable Backend**: Laravel-based API with proper authentication and authorization
- **Modern Frontend**: React with TypeScript for type safety and developer experience
- **Cross-Platform Mobile**: Flutter for native performance on both platforms
- **Real-time Features**: Chat system with polling-based updates
- **Content Management**: Comprehensive media handling and moderation
- **Admin Tools**: Full-featured admin panel for content and user management

The project showcases best practices in:
- API design and documentation
- Security implementation
- Code organization and maintainability
- Cross-platform development
- User experience design

This documentation serves as a complete technical reference for developers working on the Katogo project, covering everything from initial setup to advanced features and future enhancements.

---

**Last Updated**: January 2025
**Version**: 1.0.0
**Maintainers**: Development Team