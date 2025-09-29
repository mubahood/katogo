import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/ApiService';

interface ChatHead {
  id: number;
  name: string;
  avatar?: string;
  last_message?: string;
  last_message_time?: string;
  unread_count?: number;
  status?: string;
}

const ChatsTab: React.FC = () => {
  const [chatHeads, setChatHeads] = useState<ChatHead[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedChat, setSelectedChat] = useState<ChatHead | null>(null);
  const [messages, setMessages] = useState<any[]>([]);
  const [newMessage, setNewMessage] = useState('');
  const [sendingMessage, setSendingMessage] = useState(false);

  useEffect(() => {
    loadChatHeads();
  }, []);

  const loadChatHeads = async () => {
    try {
      setLoading(true);
      const response = await ApiService.get('/chat-heads');
      if (response.data?.success) {
        setChatHeads(response.data.data || []);
      }
    } catch (err: any) {
      console.error('Failed to load chat heads:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadChatMessages = async (chatId: number) => {
    try {
      const response = await ApiService.get(`/chat-messages?chat_head_id=${chatId}`);
      if (response.data?.success) {
        setMessages(response.data.data || []);
        // Mark as read
        await ApiService.post('/chat-mark-as-read', { chat_head_id: chatId });
      }
    } catch (err: any) {
      console.error('Failed to load messages:', err);
    }
  };

  const sendMessage = async () => {
    if (!newMessage.trim() || !selectedChat || sendingMessage) return;

    try {
      setSendingMessage(true);
      const response = await ApiService.post('/chat-send', {
        chat_head_id: selectedChat.id,
        message: newMessage.trim()
      });

      if (response.data?.success) {
        setMessages(prev => [...prev, response.data.data]);
        setNewMessage('');
        // Refresh chat heads to update last message
        loadChatHeads();
      }
    } catch (err: any) {
      console.error('Failed to send message:', err);
    } finally {
      setSendingMessage(false);
    }
  };

  const selectChat = (chat: ChatHead) => {
    setSelectedChat(chat);
    loadChatMessages(chat.id);
  };

  const formatMessageTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (loading) {
    return (
      <div className="chats-loading">
        <div className="loading-spinner"></div>
        <p>Loading your chats...</p>
      </div>
    );
  }

  return (
    <div className="chats-tab">
      <div className="chats-header">
        <h2 className="chats-title">
          <span className="title-icon">💬</span>
          Messages
        </h2>
        <button className="new-chat-button">
          <span className="new-icon">✉️</span>
          New Chat
        </button>
      </div>

      <div className="chats-container">
        {/* Chat List */}
        <div className="chat-list">
          {chatHeads.length > 0 ? (
            chatHeads.map((chat) => (
              <div 
                key={chat.id} 
                className={`chat-item ${selectedChat?.id === chat.id ? 'active' : ''}`}
                onClick={() => selectChat(chat)}
              >
                <div className="chat-avatar">
                  {chat.avatar ? (
                    <img src={chat.avatar} alt={chat.name} />
                  ) : (
                    <div className="avatar-placeholder">
                      {chat.name.charAt(0).toUpperCase()}
                    </div>
                  )}
                  {chat.status === 'online' && <div className="status-indicator"></div>}
                </div>
                
                <div className="chat-info">
                  <div className="chat-header-row">
                    <h4 className="chat-name">{chat.name}</h4>
                    {chat.last_message_time && (
                      <span className="chat-time">
                        {formatMessageTime(chat.last_message_time)}
                      </span>
                    )}
                  </div>
                  
                  <div className="chat-preview-row">
                    <p className="last-message">
                      {chat.last_message || 'No messages yet'}
                    </p>
                    {chat.unread_count && chat.unread_count > 0 && (
                      <span className="unread-badge">{chat.unread_count}</span>
                    )}
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className="empty-chat-list">
              <div className="empty-icon">💬</div>
              <p>No conversations yet</p>
            </div>
          )}
        </div>

        {/* Chat Messages */}
        <div className="chat-messages">
          {selectedChat ? (
            <>
              <div className="chat-messages-header">
                <div className="chat-user-info">
                  <div className="chat-user-avatar">
                    {selectedChat.avatar ? (
                      <img src={selectedChat.avatar} alt={selectedChat.name} />
                    ) : (
                      <div className="avatar-placeholder">
                        {selectedChat.name.charAt(0).toUpperCase()}
                      </div>
                    )}
                  </div>
                  <div className="chat-user-details">
                    <h3>{selectedChat.name}</h3>
                    <p>{selectedChat.status || 'Unknown'}</p>
                  </div>
                </div>
              </div>
              
              <div className="messages-container">
                {messages.length > 0 ? (
                  messages.map((message, index) => (
                    <div 
                      key={index} 
                      className={`message ${message.is_me ? 'sent' : 'received'}`}
                    >
                      <div className="message-content">
                        <p>{message.message}</p>
                        <span className="message-time">
                          {formatMessageTime(message.created_at)}
                        </span>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="no-messages">
                    <p>Start a conversation with {selectedChat.name}</p>
                  </div>
                )}
              </div>
              
              <div className="message-input-container">
                <div className="message-input-row">
                  <input
                    type="text"
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                    placeholder={`Message ${selectedChat.name}...`}
                    className="message-input"
                    onKeyPress={(e) => e.key === 'Enter' && sendMessage()}
                    disabled={sendingMessage}
                  />
                  <button 
                    className="send-button"
                    onClick={sendMessage}
                    disabled={!newMessage.trim() || sendingMessage}
                  >
                    {sendingMessage ? '⏳' : '📤'}
                  </button>
                </div>
              </div>
            </>
          ) : (
            <div className="no-chat-selected">
              <div className="no-chat-icon">💬</div>
              <h3>Select a conversation</h3>
              <p>Choose a chat from the list to start messaging</p>
            </div>
          )}
        </div>
      </div>

      <style jsx>{`
        .chats-tab {
          height: 100%;
          display: flex;
          flex-direction: column;
        }

        .chats-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 1.5rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chats-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .title-icon {
          font-size: 1.75rem;
        }

        .new-chat-button {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .new-chat-button:hover {
          transform: translateY(-2px);
        }

        .new-icon {
          font-size: 1rem;
        }

        .chats-container {
          display: flex;
          flex: 1;
          gap: 1.5rem;
          min-height: 0;
        }

        .chat-list {
          width: 350px;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow-y: auto;
          flex-shrink: 0;
        }

        .chat-item {
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.05);
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .chat-item:hover {
          background: rgba(255, 255, 255, 0.1);
        }

        .chat-item.active {
          background: rgba(102, 126, 234, 0.2);
          border-color: rgba(102, 126, 234, 0.3);
        }

        .chat-avatar {
          position: relative;
          width: 50px;
          height: 50px;
          flex-shrink: 0;
        }

        .chat-avatar img {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          object-fit: cover;
        }

        .avatar-placeholder {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.25rem;
          font-weight: bold;
          color: white;
        }

        .status-indicator {
          position: absolute;
          bottom: 2px;
          right: 2px;
          width: 12px;
          height: 12px;
          background: #4ade80;
          border: 2px solid rgba(255, 255, 255, 0.2);
          border-radius: 50%;
        }

        .chat-info {
          flex: 1;
          min-width: 0;
        }

        .chat-header-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 0.25rem;
        }

        .chat-name {
          font-size: 1rem;
          font-weight: 600;
          margin: 0;
          color: #ffffff;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .chat-time {
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.6);
          flex-shrink: 0;
        }

        .chat-preview-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .last-message {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          flex: 1;
        }

        .unread-badge {
          background: #ff6b6b;
          color: white;
          border-radius: 12px;
          padding: 0.2rem 0.5rem;
          font-size: 0.7rem;
          font-weight: 600;
          min-width: 18px;
          text-align: center;
        }

        .empty-chat-list {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 3rem 1rem;
          text-align: center;
        }

        .empty-icon {
          font-size: 3rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-chat-list p {
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .chat-messages {
          flex: 1;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          display: flex;
          flex-direction: column;
          min-height: 0;
        }

        .chat-messages-header {
          padding: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chat-user-info {
          display: flex;
          align-items: center;
          gap: 1rem;
        }

        .chat-user-avatar {
          width: 40px;
          height: 40px;
        }

        .chat-user-avatar img {
          width: 100%;
          height: 100%;
          border-radius: 50%;
          object-fit: cover;
        }

        .chat-user-details h3 {
          font-size: 1.1rem;
          font-weight: 600;
          margin: 0;
          color: #ffffff;
        }

        .chat-user-details p {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .messages-container {
          flex: 1;
          overflow-y: auto;
          padding: 1rem;
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }

        .message {
          display: flex;
          max-width: 70%;
        }

        .message.sent {
          align-self: flex-end;
        }

        .message.received {
          align-self: flex-start;
        }

        .message-content {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.75rem 1rem;
          border-radius: 12px;
          position: relative;
        }

        .message.sent .message-content {
          background: rgba(102, 126, 234, 0.8);
        }

        .message-content p {
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1.4;
        }

        .message-time {
          font-size: 0.7rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .no-messages {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100%;
          text-align: center;
          color: rgba(255, 255, 255, 0.6);
        }

        .message-input-container {
          padding: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .message-input-row {
          display: flex;
          gap: 0.5rem;
        }

        .message-input {
          flex: 1;
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 20px;
          padding: 0.75rem 1rem;
          color: #ffffff;
          font-size: 1rem;
          transition: all 0.3s ease;
        }

        .message-input:focus {
          outline: none;
          border-color: rgba(102, 126, 234, 0.8);
          background: rgba(255, 255, 255, 0.15);
        }

        .message-input::placeholder {
          color: rgba(255, 255, 255, 0.5);
        }

        .send-button {
          background: rgba(102, 126, 234, 0.8);
          border: none;
          color: white;
          border-radius: 50%;
          width: 45px;
          height: 45px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.2s ease;
          font-size: 1rem;
        }

        .send-button:hover:not(:disabled) {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.05);
        }

        .send-button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          transform: none;
        }

        .no-chat-selected {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 100%;
          text-align: center;
          padding: 2rem;
        }

        .no-chat-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .no-chat-selected h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .no-chat-selected p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          font-size: 1.1rem;
        }

        .chats-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 3px solid rgba(255, 255, 255, 0.3);
          border-top: 3px solid #ffffff;
          border-radius: 50%;
          animation: spin 1s linear infinite;
          margin-bottom: 1rem;
        }

        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
          .chats-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .chats-title {
            font-size: 1.5rem;
          }

          .chats-container {
            flex-direction: column;
            height: 500px;
          }

          .chat-list {
            width: 100%;
            height: 200px;
          }

          .chat-messages {
            height: 300px;
          }

          .message {
            max-width: 85%;
          }
        }
      `}</style>
    </div>
  );
};

export default ChatsTab;