import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/ApiService';

interface WatchHistoryItem {
  id: number;
  movie_id: number;
  movie_title: string;
  movie_thumbnail: string;
  movie_year: number;
  movie_type: string;
  movie_category: string;
  episode_number?: number;
  progress: number;
  max_progress: number;
  percentage: number;
  status: string;
  last_watched_at: string;
  device: string;
  platform: string;
}

interface WatchHistoryData {
  items: WatchHistoryItem[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

const WatchHistoryTab: React.FC = () => {
  const [historyData, setHistoryData] = useState<WatchHistoryData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [filter, setFilter] = useState<'all' | 'completed' | 'in-progress'>('all');

  useEffect(() => {
    loadWatchHistory();
  }, [currentPage, filter]);

  const loadWatchHistory = async () => {
    try {
      setLoading(true);
      setError(null);
      
      let url = `/watch-history?page=${currentPage}&per_page=15`;
      if (filter !== 'all') {
        url += `&status=${filter}`;
      }
      
      const response = await ApiService.get(url);
      if (response.data?.success) {
        setHistoryData(response.data.data);
      } else {
        setError('Failed to load watch history');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load watch history');
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const formatDuration = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
  };

  const getStatusColor = (percentage: number) => {
    if (percentage >= 90) return '#4ade80'; // Green for completed
    if (percentage >= 10) return '#fbbf24'; // Yellow for in-progress
    return '#94a3b8'; // Gray for barely started
  };

  const getStatusText = (percentage: number) => {
    if (percentage >= 90) return 'Completed';
    if (percentage >= 10) return 'In Progress';
    return 'Started';
  };

  const renderPagination = () => {
    if (!historyData || historyData.last_page <= 1) return null;

    const pages = [];
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(historyData.last_page, startPage + maxVisible - 1);

    if (endPage - startPage + 1 < maxVisible) {
      startPage = Math.max(1, endPage - maxVisible + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }

    return (
      <div className="pagination">
        <button
          className="pagination-button"
          onClick={() => setCurrentPage(1)}
          disabled={currentPage === 1}
        >
          ⏮️
        </button>
        <button
          className="pagination-button"
          onClick={() => setCurrentPage(currentPage - 1)}
          disabled={currentPage === 1}
        >
          ⬅️
        </button>
        
        {pages.map(page => (
          <button
            key={page}
            className={`pagination-button ${currentPage === page ? 'active' : ''}`}
            onClick={() => setCurrentPage(page)}
          >
            {page}
          </button>
        ))}
        
        <button
          className="pagination-button"
          onClick={() => setCurrentPage(currentPage + 1)}
          disabled={currentPage === historyData.last_page}
        >
          ➡️
        </button>
        <button
          className="pagination-button"
          onClick={() => setCurrentPage(historyData.last_page)}
          disabled={currentPage === historyData.last_page}
        >
          ⏭️
        </button>
      </div>
    );
  };

  if (loading) {
    return (
      <div className="history-loading">
        <div className="loading-spinner"></div>
        <p>Loading your watch history...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="history-error">
        <p>❌ {error}</p>
        <button className="retry-button" onClick={loadWatchHistory}>
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="history-tab">
      <div className="history-header">
        <h2 className="history-title">
          <span className="title-icon">🕒</span>
          Watch History
        </h2>
        
        <div className="history-controls">
          <div className="filter-buttons">
            <button 
              className={`filter-button ${filter === 'all' ? 'active' : ''}`}
              onClick={() => setFilter('all')}
            >
              All
            </button>
            <button 
              className={`filter-button ${filter === 'in-progress' ? 'active' : ''}`}
              onClick={() => setFilter('in-progress')}
            >
              In Progress
            </button>
            <button 
              className={`filter-button ${filter === 'completed' ? 'active' : ''}`}
              onClick={() => setFilter('completed')}
            >
              Completed
            </button>
          </div>
          
          {historyData && (
            <div className="history-stats">
              <span className="total-count">{historyData.total} items</span>
            </div>
          )}
        </div>
      </div>

      {historyData && historyData.items.length > 0 ? (
        <>
          <div className="history-list">
            {historyData.items.map((item) => (
              <div key={item.id} className="history-item">
                <div className="item-poster">
                  <img 
                    src={item.movie_thumbnail} 
                    alt={item.movie_title}
                    onError={(e) => {
                      (e.target as HTMLImageElement).style.display = 'none';
                    }}
                  />
                  <div className="progress-overlay">
                    <div 
                      className="progress-bar"
                      style={{ 
                        width: `${Math.min(Math.max(item.percentage, 0), 100)}%`,
                        backgroundColor: getStatusColor(item.percentage)
                      }}
                    ></div>
                  </div>
                  <div className="play-overlay">
                    <button className="play-button">▶️</button>
                  </div>
                </div>
                
                <div className="item-content">
                  <div className="item-main">
                    <h3 className="item-title">{item.movie_title}</h3>
                    <div className="item-meta">
                      <span className="item-year">{item.movie_year}</span>
                      <span className="item-type">{item.movie_type}</span>
                      <span className="item-category">{item.movie_category}</span>
                      {item.episode_number && (
                        <span className="item-episode">Ep. {item.episode_number}</span>
                      )}
                    </div>
                    
                    <div className="progress-info">
                      <div className="progress-text">
                        <span 
                          className="status-badge"
                          style={{ backgroundColor: getStatusColor(item.percentage) }}
                        >
                          {getStatusText(item.percentage)}
                        </span>
                        <span className="progress-percentage">
                          {Math.round(item.percentage)}% watched
                        </span>
                      </div>
                      
                      <div className="time-info">
                        {item.progress > 0 && (
                          <span className="time-watched">
                            {formatDuration(item.progress)} watched
                          </span>
                        )}
                        {item.max_progress > 0 && (
                          <span className="total-duration">
                            of {formatDuration(item.max_progress)}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  <div className="item-sidebar">
                    <div className="watch-info">
                      <div className="last-watched">
                        <span className="watch-label">Last watched</span>
                        <span className="watch-time">{formatDate(item.last_watched_at)}</span>
                      </div>
                      
                      <div className="device-info">
                        <span className="device-label">Device</span>
                        <span className="device-name">
                          📱 {item.device || 'Unknown'} • {item.platform || 'Web'}
                        </span>
                      </div>
                    </div>
                    
                    <div className="item-actions">
                      <button className="continue-button" title="Continue Watching">
                        ▶️ Continue
                      </button>
                      <button className="remove-button" title="Remove from History">
                        🗑️
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>

          {renderPagination()}
        </>
      ) : (
        <div className="empty-history">
          <div className="empty-icon">🕒</div>
          <h3>No watch history yet</h3>
          <p>Movies you watch will appear here with your progress</p>
          <button className="browse-button">
            Browse Movies
          </button>
        </div>
      )}

      <style jsx>{`
        .history-tab {
          height: 100%;
          overflow-y: auto;
        }

        .history-header {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          gap: 1rem;
        }

        .history-title {
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

        .history-controls {
          display: flex;
          flex-direction: column;
          align-items: flex-end;
          gap: 1rem;
        }

        .filter-buttons {
          display: flex;
          gap: 0.5rem;
          background: rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 0.25rem;
        }

        .filter-button {
          background: transparent;
          border: none;
          color: rgba(255, 255, 255, 0.7);
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 0.9rem;
          font-weight: 500;
        }

        .filter-button:hover {
          color: #ffffff;
          background: rgba(255, 255, 255, 0.1);
        }

        .filter-button.active {
          background: rgba(102, 126, 234, 0.8);
          color: #ffffff;
          font-weight: 600;
        }

        .history-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
          font-size: 0.9rem;
        }

        .history-list {
          display: flex;
          flex-direction: column;
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .history-item {
          display: flex;
          gap: 1.5rem;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 1.5rem;
          transition: all 0.3s ease;
        }

        .history-item:hover {
          background: rgba(255, 255, 255, 0.1);
          border-color: rgba(255, 255, 255, 0.2);
          transform: translateY(-2px);
        }

        .item-poster {
          position: relative;
          width: 120px;
          height: 160px;
          border-radius: 8px;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          flex-shrink: 0;
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
        }

        .progress-overlay {
          position: absolute;
          bottom: 0;
          left: 0;
          right: 0;
          height: 4px;
          background: rgba(0, 0, 0, 0.5);
        }

        .progress-bar {
          height: 100%;
          transition: width 0.3s ease;
        }

        .play-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          opacity: 0;
          transition: opacity 0.3s ease;
        }

        .history-item:hover .play-overlay {
          opacity: 1;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          border: none;
          border-radius: 50%;
          width: 50px;
          height: 50px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1.25rem;
          color: white;
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
        }

        .item-content {
          flex: 1;
          display: flex;
          gap: 2rem;
        }

        .item-main {
          flex: 1;
          min-width: 0;
        }

        .item-title {
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 0.75rem 0;
          color: #ffffff;
          line-height: 1.3;
        }

        .item-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          margin-bottom: 1rem;
        }

        .item-year,
        .item-type,
        .item-category,
        .item-episode {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .progress-info {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .progress-text {
          display: flex;
          align-items: center;
          gap: 1rem;
        }

        .status-badge {
          padding: 0.25rem 0.75rem;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
          color: white;
        }

        .progress-percentage {
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .time-info {
          display: flex;
          gap: 0.5rem;
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
        }

        .item-sidebar {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          align-items: flex-end;
          min-width: 200px;
        }

        .watch-info {
          text-align: right;
        }

        .last-watched,
        .device-info {
          margin-bottom: 0.75rem;
        }

        .watch-label,
        .device-label {
          display: block;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.5);
          text-transform: uppercase;
          letter-spacing: 0.5px;
          margin-bottom: 0.25rem;
        }

        .watch-time,
        .device-name {
          display: block;
          font-size: 0.9rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .item-actions {
          display: flex;
          gap: 0.5rem;
        }

        .continue-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 0.5rem 1rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .continue-button:hover {
          transform: translateY(-1px);
        }

        .remove-button {
          background: rgba(255, 107, 107, 0.8);
          border: none;
          color: white;
          padding: 0.5rem;
          border-radius: 6px;
          cursor: pointer;
          font-size: 0.85rem;
          transition: all 0.2s ease;
        }

        .remove-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: translateY(-1px);
        }

        .pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 0.5rem;
          margin-top: 2rem;
          padding-top: 1rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pagination-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: rgba(255, 255, 255, 0.8);
          padding: 0.5rem 0.75rem;
          border-radius: 6px;
          cursor: pointer;
          transition: all 0.3s ease;
          min-width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .pagination-button:hover:not(:disabled) {
          background: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .pagination-button.active {
          background: rgba(102, 126, 234, 0.8);
          border-color: rgba(102, 126, 234, 1);
          color: #ffffff;
        }

        .pagination-button:disabled {
          opacity: 0.4;
          cursor: not-allowed;
        }

        .empty-history {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          min-height: 400px;
          padding: 2rem;
        }

        .empty-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
          opacity: 0.5;
        }

        .empty-history h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-history p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0 0 2rem 0;
          font-size: 1.1rem;
        }

        .browse-button {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border: none;
          color: white;
          padding: 1rem 2rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .browse-button:hover {
          transform: translateY(-2px);
        }

        .history-loading,
        .history-error {
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
          .history-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .history-title {
            font-size: 1.5rem;
          }

          .history-controls {
            align-items: flex-start;
            width: 100%;
          }

          .filter-buttons {
            width: 100%;
            justify-content: space-between;
          }

          .history-item {
            flex-direction: column;
            gap: 1rem;
          }

          .item-poster {
            width: 100px;
            height: 130px;
            align-self: flex-start;
          }

          .item-content {
            flex-direction: column;
            gap: 1rem;
          }

          .item-sidebar {
            align-items: flex-start;
            min-width: auto;
          }

          .watch-info {
            text-align: left;
          }

          .item-actions {
            justify-content: flex-start;
          }
        }
      `}</style>
    </div>
  );
};

export default WatchHistoryTab;