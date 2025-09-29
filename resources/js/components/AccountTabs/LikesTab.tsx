import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/ApiService';

interface LikedMovie {
  like_id: number;
  movie_id: number;
  title: string;
  thumbnail: string;
  year: number;
  type: string;
  category: string;
  episode_number?: number;
  liked_at: string;
}

const LikesTab: React.FC = () => {
  const [likedMovies, setLikedMovies] = useState<LikedMovie[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadLikedMovies();
  }, []);

  const loadLikedMovies = async () => {
    try {
      setLoading(true);
      const response = await ApiService.get('/account/likes');
      if (response.data?.success) {
        setLikedMovies(response.data.data.items);
      } else {
        setError('Failed to load liked movies');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load liked movies');
    } finally {
      setLoading(false);
    }
  };

  const toggleLike = async (movieId: number) => {
    try {
      const response = await ApiService.post('/account/likes/toggle', { movie_id: movieId });
      if (response.data?.success) {
        setLikedMovies(prev => prev.filter(movie => movie.movie_id !== movieId));
      }
    } catch (err: any) {
      console.error('Failed to unlike movie:', err);
    }
  };

  if (loading) {
    return (
      <div className="likes-loading">
        <div className="loading-spinner"></div>
        <p>Loading your liked movies...</p>
      </div>
    );
  }

  return (
    <div className="likes-tab">
      <div className="likes-header">
        <h2 className="likes-title">
          <span className="title-icon">❤️</span>
          Liked Movies
        </h2>
        <div className="likes-stats">
          <span className="total-count">{likedMovies.length} movies</span>
        </div>
      </div>

      {likedMovies.length > 0 ? (
        <div className="likes-grid">
          {likedMovies.map((movie) => (
            <div key={movie.like_id} className="like-item">
              <div className="item-poster">
                <img src={movie.thumbnail} alt={movie.title} />
                <div className="item-overlay">
                  <button 
                    className="unlike-button"
                    onClick={() => toggleLike(movie.movie_id)}
                    title="Unlike"
                  >
                    💔
                  </button>
                  <button className="play-button" title="Play">
                    ▶️
                  </button>
                </div>
              </div>
              <div className="item-details">
                <h3 className="item-title">{movie.title}</h3>
                <div className="item-meta">
                  <span className="item-year">{movie.year}</span>
                  <span className="item-type">{movie.type}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="empty-likes">
          <div className="empty-icon">❤️</div>
          <h3>No liked movies yet</h3>
          <p>Movies you like will appear here</p>
        </div>
      )}

      <style jsx>{`
        .likes-tab {
          height: 100%;
          overflow-y: auto;
        }

        .likes-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .likes-title {
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

        .likes-stats {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.5rem 1rem;
          border-radius: 20px;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .total-count {
          color: rgba(255, 255, 255, 0.9);
          font-weight: 600;
        }

        .likes-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
          gap: 1.5rem;
        }

        .like-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .like-item:hover {
          background: rgba(255, 255, 255, 0.1);
          transform: translateY(-4px);
        }

        .item-poster {
          position: relative;
          aspect-ratio: 2/3;
          overflow: hidden;
          background: rgba(255, 255, 255, 0.1);
        }

        .item-poster img {
          width: 100%;
          height: 100%;
          object-fit: cover;
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

        .like-item:hover .item-overlay {
          opacity: 1;
        }

        .unlike-button,
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

        .unlike-button {
          background: rgba(255, 107, 107, 0.9);
          color: white;
        }

        .play-button {
          background: rgba(102, 126, 234, 0.9);
          color: white;
        }

        .unlike-button:hover {
          background: rgba(255, 107, 107, 1);
          transform: scale(1.1);
        }

        .play-button:hover {
          background: rgba(102, 126, 234, 1);
          transform: scale(1.1);
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
          gap: 0.5rem;
        }

        .item-year,
        .item-type {
          background: rgba(255, 255, 255, 0.1);
          padding: 0.25rem 0.5rem;
          border-radius: 12px;
          font-size: 0.75rem;
          color: rgba(255, 255, 255, 0.8);
        }

        .empty-likes {
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

        .empty-likes h3 {
          font-size: 1.5rem;
          margin: 0 0 1rem 0;
          color: #ffffff;
        }

        .empty-likes p {
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
          font-size: 1.1rem;
        }

        .likes-loading {
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
          .likes-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .likes-title {
            font-size: 1.5rem;
          }

          .likes-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
          }
        }
      `}</style>
    </div>
  );
};

export default LikesTab;