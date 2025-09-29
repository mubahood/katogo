import React from 'react';

interface DashboardTabProps {
  data: {
    stats: {
      watchlist_count: number;
      likes_count: number;
      watch_history_count: number;
    };
    recent_activity: {
      recent_watched: Array<{
        movie_id: number;
        title: string;
        thumbnail: string;
        progress: number;
        last_watched: string;
      }>;
      recent_likes: Array<{
        movie_id: number;
        title: string;
        thumbnail: string;
        liked_at: string;
      }>;
    };
    user: {
      id: number;
      name: string;
      email: string;
      avatar?: string;
      member_since: string;
    };
  } | null;
  onRefresh: () => void;
}

const DashboardTab: React.FC<DashboardTabProps> = ({ data, onRefresh }) => {
  if (!data) {
    return <div>Loading dashboard...</div>;
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const formatMemberSince = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long'
    });
  };

  return (
    <div className="dashboard-tab">
      <div className="dashboard-header">
        <h2 className="dashboard-title">
          <span className="title-icon">👋</span>
          Welcome back, {data.user.name}!
        </h2>
        <button 
          className="refresh-button"
          onClick={onRefresh}
          title="Refresh Dashboard"
        >
          🔄
        </button>
      </div>

      {/* Stats Cards */}
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-icon">📺</div>
          <div className="stat-content">
            <h3 className="stat-number">{data.stats.watchlist_count}</h3>
            <p className="stat-label">Movies in Watchlist</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon">❤️</div>
          <div className="stat-content">
            <h3 className="stat-number">{data.stats.likes_count}</h3>
            <p className="stat-label">Liked Movies</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon">🕒</div>
          <div className="stat-content">
            <h3 className="stat-number">{data.stats.watch_history_count}</h3>
            <p className="stat-label">Movies Watched</p>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon">👤</div>
          <div className="stat-content">
            <h3 className="stat-number">Member</h3>
            <p className="stat-label">Since {formatMemberSince(data.user.member_since)}</p>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="activity-section">
        <h3 className="section-title">
          <span className="section-icon">⚡</span>
          Recent Activity
        </h3>

        <div className="activity-grid">
          {/* Recently Watched */}
          <div className="activity-card">
            <h4 className="activity-title">
              <span className="activity-icon">▶️</span>
              Continue Watching
            </h4>
            {data.recent_activity.recent_watched.length > 0 ? (
              <div className="activity-list">
                {data.recent_activity.recent_watched.map((item) => (
                  <div key={item.movie_id} className="activity-item">
                    <div className="item-thumbnail">
                      <img 
                        src={item.thumbnail} 
                        alt={item.title}
                        onError={(e) => {
                          (e.target as HTMLImageElement).style.display = 'none';
                        }}
                      />
                      <div className="progress-bar">
                        <div 
                          className="progress-fill"
                          style={{ 
                            width: `${Math.min(Math.max(item.progress, 0), 100)}%` 
                          }}
                        ></div>
                      </div>
                    </div>
                    <div className="item-details">
                      <h5 className="item-title">{item.title}</h5>
                      <p className="item-meta">
                        {Math.round(item.progress)}% • {formatDate(item.last_watched)}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <p>No recent viewing activity</p>
              </div>
            )}
          </div>

          {/* Recently Liked */}
          <div className="activity-card">
            <h4 className="activity-title">
              <span className="activity-icon">❤️</span>
              Recently Liked
            </h4>
            {data.recent_activity.recent_likes.length > 0 ? (
              <div className="activity-list">
                {data.recent_activity.recent_likes.map((item) => (
                  <div key={item.movie_id} className="activity-item">
                    <div className="item-thumbnail">
                      <img 
                        src={item.thumbnail} 
                        alt={item.title}
                        onError={(e) => {
                          (e.target as HTMLImageElement).style.display = 'none';
                        }}
                      />
                    </div>
                    <div className="item-details">
                      <h5 className="item-title">{item.title}</h5>
                      <p className="item-meta">
                        Liked on {formatDate(item.liked_at)}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <p>No liked movies yet</p>
              </div>
            )}
          </div>
        </div>
      </div>

      <style jsx>{`
        .dashboard-tab {
          height: 100%;
          overflow-y: auto;
        }

        .dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dashboard-title {
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

        .refresh-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: #ffffff;
          border-radius: 8px;
          padding: 0.5rem 1rem;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .refresh-button:hover {
          background: rgba(255, 255, 255, 0.2);
          transform: scale(1.05);
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
          gap: 1.5rem;
          margin-bottom: 3rem;
        }

        .stat-card {
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 12px;
          padding: 2rem;
          display: flex;
          align-items: center;
          gap: 1.5rem;
          transition: all 0.3s ease;
        }

        .stat-card:hover {
          background: rgba(255, 255, 255, 0.15);
          transform: translateY(-2px);
        }

        .stat-icon {
          font-size: 3rem;
          opacity: 0.8;
        }

        .stat-content {
          flex: 1;
        }

        .stat-number {
          font-size: 2.5rem;
          font-weight: 700;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1;
        }

        .stat-label {
          font-size: 1rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .activity-section {
          margin-top: 2rem;
        }

        .section-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.5rem;
          font-weight: 600;
          margin: 0 0 2rem 0;
          color: #ffffff;
        }

        .section-icon {
          font-size: 1.25rem;
        }

        .activity-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
          gap: 2rem;
        }

        .activity-card {
          background: rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 12px;
          padding: 1.5rem;
        }

        .activity-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 1.5rem 0;
          color: #ffffff;
        }

        .activity-icon {
          font-size: 1rem;
        }

        .activity-list {
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }

        .activity-item {
          display: flex;
          gap: 1rem;
          align-items: center;
          padding: 0.75rem;
          background: rgba(255, 255, 255, 0.05);
          border-radius: 8px;
          transition: all 0.3s ease;
        }

        .activity-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateX(4px);
        }

        .item-thumbnail {
          position: relative;
          width: 60px;
          height: 80px;
          border-radius: 6px;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .item-thumbnail img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .progress-bar {
          position: absolute;
          bottom: 0;
          left: 0;
          right: 0;
          height: 3px;
          background: rgba(0, 0, 0, 0.5);
        }

        .progress-fill {
          height: 100%;
          background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
          transition: width 0.3s ease;
        }

        .item-details {
          flex: 1;
          min-width: 0;
        }

        .item-title {
          font-size: 1rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .item-meta {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin: 0;
        }

        .empty-state {
          text-align: center;
          padding: 2rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .empty-state p {
          margin: 0;
          font-style: italic;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .dashboard-title {
            font-size: 1.5rem;
          }

          .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
          }

          .stat-card {
            padding: 1.5rem;
            gap: 1rem;
          }

          .stat-icon {
            font-size: 2.5rem;
          }

          .stat-number {
            font-size: 2rem;
          }

          .activity-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
          }

          .activity-card {
            padding: 1rem;
          }

          .activity-item {
            gap: 0.75rem;
          }

          .item-thumbnail {
            width: 50px;
            height: 65px;
          }
        }
      `}</style>
    </div>
  );
};

export default DashboardTab;