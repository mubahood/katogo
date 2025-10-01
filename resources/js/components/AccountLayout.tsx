import React, { useState, useEffect } from 'react';
import { ApiService } from '../services/ApiService';
import DashboardTab from './AccountTabs/DashboardTab';
import ProfileTab from './AccountTabs/ProfileTab';
import WatchlistTab from './AccountTabs/WatchlistTab';
import WatchHistoryTab from './AccountTabs/WatchHistoryTab';
import LikesTab from './AccountTabs/LikesTab';
import ProductsTab from './AccountTabs/ProductsTab';
import ChatsTab from './AccountTabs/ChatsTab';

interface AccountLayoutProps {
  initialTab?: string;
  onClose?: () => void;
}

interface DashboardData {
  stats: {
    watchlist_count: number;
    likes_count: number;
    watch_history_count: number;
  };
  recent_activity: {
    recent_watched: Array<any>;
    recent_likes: Array<any>;
  };
  user: {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    member_since: string;
  };
}

const AccountLayout: React.FC<AccountLayoutProps> = ({ 
  initialTab = 'dashboard', 
  onClose 
}) => {
  const [activeTab, setActiveTab] = useState<string>(initialTab);
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const tabs = [
    { id: 'dashboard', label: 'Dashboard', icon: '📊' },
    { id: 'profile', label: 'Profile', icon: '👤' },
    { id: 'watchlist', label: 'Watchlist', icon: '📺' },
    { id: 'history', label: 'History', icon: '🕒' },
    { id: 'likes', label: 'Likes', icon: '❤️' },
    { id: 'products', label: 'Products', icon: '🛍️' },
    { id: 'chats', label: 'Chats', icon: '💬' },
  ];

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      const response = await ApiService.get('/account/dashboard');
      if (response.data?.success) {
        setDashboardData(response.data.data);
      } else {
        setError('Failed to load dashboard data');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load dashboard');
    } finally {
      setLoading(false);
    }
  };

  const renderActiveTab = () => {
    if (loading) {
      return (
        <div className="account-loading">
          <div className="loading-spinner"></div>
          <p>Loading your account data...</p>
        </div>
      );
    }

    if (error) {
      return (
        <div className="account-error">
          <p>❌ {error}</p>
          <button 
            className="retry-button"
            onClick={loadDashboardData}
          >
            Retry
          </button>
        </div>
      );
    }

    switch (activeTab) {
      case 'dashboard':
        return <DashboardTab data={dashboardData} onRefresh={loadDashboardData} />;
      case 'profile':
        return <ProfileTab user={dashboardData?.user} onUpdate={loadDashboardData} />;
      case 'watchlist':
        return <WatchlistTab />;
      case 'history':
        return <WatchHistoryTab />;
      case 'likes':
        return <LikesTab />;
      case 'products':
        return <ProductsTab />;
      case 'chats':
        return <ChatsTab />;
      default:
        return <DashboardTab data={dashboardData} onRefresh={loadDashboardData} />;
    }
  };

  return (
    <div className="account-layout">
      {/* Header */}
      <div className="account-header">
        <div className="account-header-content">
          <h1 className="account-title">
            <span className="account-icon">⚙️</span>
            Account
          </h1>
          {onClose && (
            <button 
              className="account-close-button"
              onClick={onClose}
              aria-label="Close Account"
            >
              ✕
            </button>
          )}
        </div>
      </div>

      <div className="account-container">
        {/* Sidebar Navigation */}
        <div className="account-sidebar">
          <div className="account-user-info">
            {dashboardData?.user && (
              <>
                <div className="user-avatar">
                  {dashboardData.user.avatar ? (
                    <img 
                      src={dashboardData.user.avatar} 
                      alt={dashboardData.user.name}
                      className="avatar-image"
                    />
                  ) : (
                    <div className="avatar-placeholder">
                      {dashboardData.user.name.charAt(0).toUpperCase()}
                    </div>
                  )}
                </div>
                <div className="user-details">
                  <h3 className="user-name">{dashboardData.user.name}</h3>
                  <p className="user-email">{dashboardData.user.email}</p>
                </div>
              </>
            )}
          </div>

          <nav className="account-nav">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                className={`nav-tab ${activeTab === tab.id ? 'active' : ''}`}
                onClick={() => setActiveTab(tab.id)}
              >
                <span className="tab-icon">{tab.icon}</span>
                <span className="tab-label">{tab.label}</span>
                {tab.id === 'watchlist' && dashboardData?.stats.watchlist_count > 0 && (
                  <span className="tab-badge">{dashboardData.stats.watchlist_count}</span>
                )}
                {tab.id === 'likes' && dashboardData?.stats.likes_count > 0 && (
                  <span className="tab-badge">{dashboardData.stats.likes_count}</span>
                )}
              </button>
            ))}
          </nav>
        </div>

        {/* Main Content */}
        <div className="account-main">
          <div className="account-content">
            {renderActiveTab()}
          </div>
        </div>
      </div>

      <style jsx>{`
        .account-layout {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
          color: #ffffff;
          z-index: 1000;
          overflow: hidden;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .account-header {
          background: rgba(0, 0, 0, 0.3);
          backdrop-filter: blur(10px);
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          padding: 1rem 0;
        }

        .account-header-content {
          max-width: 1400px;
          margin: 0 auto;
          padding: 0 2rem;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .account-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.75rem;
          font-weight: 700;
          margin: 0;
          color: #ffffff;
        }

        .account-icon {
          font-size: 1.5rem;
        }

        .account-close-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: #ffffff;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1.2rem;
        }

        .account-close-button:hover {
          background: rgba(255, 255, 255, 0.2);
          transform: scale(1.05);
        }

        .account-container {
          display: flex;
          height: calc(100vh - 80px);
          max-width: 1400px;
          margin: 0 auto;
          padding: 0 2rem;
        }

        .account-sidebar {
          width: 280px;
          background: rgba(0, 0, 0, 0.2);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          margin: 1rem 0;
          padding: 2rem 1.5rem;
          border: 1px solid rgba(255, 255, 255, 0.1);
          overflow-y: auto;
        }

        .account-user-info {
          margin-bottom: 2rem;
          text-align: center;
          padding-bottom: 2rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
          margin-bottom: 1rem;
        }

        .avatar-image {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          object-fit: cover;
          border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-placeholder {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 2rem;
          font-weight: bold;
          color: white;
          margin: 0 auto;
          border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .user-name {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .user-email {
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .account-nav {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .nav-tab {
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.25rem;
          background: transparent;
          border: 1px solid transparent;
          border-radius: 8px;
          color: rgba(255, 255, 255, 0.8);
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
          width: 100%;
          text-align: left;
          position: relative;
        }

        .nav-tab:hover {
          background: rgba(255, 255, 255, 0.1);
          border-color: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .nav-tab.active {
          background: rgba(255, 255, 255, 0.15);
          border-color: rgba(255, 255, 255, 0.3);
          color: #ffffff;
          font-weight: 600;
        }

        .tab-icon {
          font-size: 1.25rem;
          width: 24px;
          text-align: center;
        }

        .tab-label {
          flex: 1;
        }

        .tab-badge {
          background: #ff6b6b;
          color: white;
          border-radius: 12px;
          padding: 0.25rem 0.5rem;
          font-size: 0.75rem;
          font-weight: 600;
          min-width: 20px;
          text-align: center;
        }

        .account-main {
          flex: 1;
          margin: 1rem 0 1rem 1.5rem;
          background: rgba(0, 0, 0, 0.2);
          backdrop-filter: blur(10px);
          border-radius: 12px;
          border: 1px solid rgba(255, 255, 255, 0.1);
          overflow: hidden;
        }

        .account-content {
          height: 100%;
          overflow-y: auto;
          padding: 2rem;
        }

        .account-loading {
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

        .account-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 300px;
          text-align: center;
        }

        .retry-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          margin-top: 1rem;
          transition: transform 0.2s ease;
        }

        .retry-button:hover {
          transform: translateY(-2px);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .account-layout {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
          }

          .account-container {
            flex-direction: column;
            padding: 0 1rem;
          }

          .account-sidebar {
            width: 100%;
            margin: 0.5rem 0;
            padding: 1rem;
          }

          .account-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
          }

          .user-avatar {
            margin-bottom: 0;
          }

          .avatar-image,
          .avatar-placeholder {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
          }

          .user-name {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
          }

          .user-email {
            font-size: 0.85rem;
          }

          .account-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
          }

          .nav-tab {
            padding: 0.75rem;
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
          }

          .tab-label {
            font-size: 0.9rem;
          }

          .account-main {
            margin: 0.5rem 0;
            flex: 1;
            min-height: 400px;
          }

          .account-content {
            padding: 1rem;
          }
        }
      `}</style>
    </div>
  );
};

export default AccountLayout;