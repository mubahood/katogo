import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/ApiService';

interface WatchlistItem {
  watchlist_id: number;
  movie_id: number;
  title: string;
  thumbnail: string;
  year: number;
  type: string;
  category: string;
  episode_number?: number;
  added_at: string;
}

interface WatchlistData {
  items: WatchlistItem[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

const WatchlistTab: React.FC = () => {
  const [watchlistData, setWatchlistData] = useState<WatchlistData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [removingId, setRemovingId] = useState<number | null>(null);

  useEffect(() => {
    loadWatchlist();
  }, [currentPage]);

  const loadWatchlist = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await ApiService.get(`/account/watchlist?page=${currentPage}&per_page=12`);
      if (response.data?.success) {
        setWatchlistData(response.data.data);
      } else {
        setError('Failed to load watchlist');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load watchlist');
    } finally {
      setLoading(false);
    }
  };

  const removeFromWatchlist = async (movieId: number) => {
    try {
      setRemovingId(movieId);
      const response = await ApiService.delete(`/account/watchlist/${movieId}`);
      if (response.data?.success) {
        // Remove from local state
        if (watchlistData) {
          const updatedItems = watchlistData.items.filter(item => item.movie_id !== movieId);
          setWatchlistData({
            ...watchlistData,
            items: updatedItems,
            total: watchlistData.total - 1
          });
        }
      } else {
        throw new Error('Failed to remove from watchlist');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to remove from watchlist');
    } finally {
      setRemovingId(null);
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const renderPagination = () => {
    if (!watchlistData || watchlistData.last_page <= 1) return null;

    const pages = [];
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(watchlistData.last_page, startPage + maxVisible - 1);

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
          disabled={currentPage === watchlistData.last_page}
        >
          ➡️
        </button>
        <button
          className="pagination-button"
          onClick={() => setCurrentPage(watchlistData.last_page)}
          disabled={currentPage === watchlistData.last_page}
        >
          ⏭️
        </button>
      </div>
    );
  };

  if (loading) {
    return (
      <div className="watchlist-loading">
        <div className="loading-spinner"></div>
        <p>Loading your watchlist...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="watchlist-error">
        <p>❌ {error}</p>
        <button className="retry-button" onClick={loadWatchlist}>
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="watchlist-tab">
      <div className="watchlist-header">
        <h2 className="watchlist-title">
          <span className="title-icon">📺</span>
          My Watchlist
        </h2>
        {watchlistData && (
          <div className="watchlist-stats">
            <span className="total-count">{watchlistData.total} movies</span>
          </div>
        )}
      </div>

      {watchlistData && watchlistData.items.length > 0 ? (
        <>
          <div className="watchlist-grid">
            {watchlistData.items.map((item) => (
              <div key={item.watchlist_id} className="watchlist-item">
                <div className="item-poster">
                  <img 
                    src={item.thumbnail} 
                    alt={item.title}
                    onError={(e) => {
                      (e.target as HTMLImageElement).style.display = 'none';
                    }}
                  />
                  <div className="item-overlay">
                    <button 
                      className="remove-button"
                      onClick={() => removeFromWatchlist(item.movie_id)}
                      disabled={removingId === item.movie_id}
                      title="Remove from Watchlist"
                    >
                      {removingId === item.movie_id ? '⏳' : '❌'}
                    </button>
                    <button className="play-button" title="Play Movie">
                      ▶️
                    </button>
                  </div>
                </div>
                
                <div className="item-details">
                  <h3 className="item-title">{item.title}</h3>
                  <div className="item-meta">
                    <span className="item-year">{item.year}</span>
                    <span className="item-type">{item.type}</span>
                    {item.episode_number && (
                      <span className="item-episode">Ep. {item.episode_number}</span>
                    )}
                  </div>
                  <div className="item-category">{item.category}</div>
                  <div className="item-added">
                    Added {formatDate(item.added_at)}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {renderPagination()}
        </>
      ) : (
        <div className="empty-watchlist">
          <div className="empty-icon">📺</div>
          <h3>Your watchlist is empty</h3>
          <p>Movies you add to your watchlist will appear here</p>
          <button className="browse-button">
            Browse Movies
          </button>
        </div>
      )}

      <style jsx>{`
        .watchlist-tab {
          height: 100%;
          overflow-y: auto;
        }

        .watchlist-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .watchlist-title {
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

        .watchlist-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
        }

        .watchlist-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .watchlist-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
          position: relative;
        }

        .watchlist-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
          border-color: rgba(255, 255, 255, 0.2);
        }

        .item-poster {
          position: relative;
          aspect-ratio: 2/3;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          transition: transform 0.3s ease;
        }

        .watchlist-item:hover .item-poster img {
          transform: scale(1.05);
        }

        .item-overlay {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.7);
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 1rem;
          opacity: 0;
          transition: opacity 0.3s ease;
        }

        .watchlist-item:hover .item-overlay {
          opacity: 1;
        }

        .remove-button,
        .play-button {
          background: rgba(255, 255, 255, 0.9);
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition: all 0.3s ease;
          font-size: 1rem;
        }

        .remove-button {
          background: rgba(255, 107, 107, 0.9);
          color: white;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          color: white;
        }

        .remove-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: scale(1.1);
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
        }

        .remove-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
          transform: none;
        }

        .item-details {
          padding: 1rem;
        }

        .item-title {
          font-size: 1rem;
          font-weight: 600;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
          line-height: 1.3;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }

        .item-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 0.5rem;
          margin-bottom: 0.5rem;
        }

        .item-year,
        .item-type,
        .item-episode {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .item-category {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.7);
          margin-bottom: 0.5rem;
        }

        .item-added {
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.5);
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

        .empty-watchlist {
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

        .empty-watchlist h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-watchlist p {
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

        .watchlist-loading,
        .watchlist-error {
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
          .watchlist-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .watchlist-title {
            font-size: 1.5rem;
          }

          .watchlist-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
          }

          .item-details {
            padding: 0.75rem;
          }

          .item-title {
            font-size: 0.9rem;
          }

          .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
          }

          .pagination-button {
            padding: 0.5rem;
            min-width: 35px;
            height: 35px;
            font-size: 0.9rem;
          }
        }
      `}</style>
    </div>
  );
};

export default WatchlistTab;