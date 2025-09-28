import React, { useState } from 'react';
import { Star, Download, Share2, Heart, Play, Users, Calendar, Clock, Globe } from 'lucide-react';
import './MovieDetails.css';

interface Cast {
  id: string;
  name: string;
  character: string;
  profile_path?: string;
}

interface Genre {
  id: string;
  name: string;
}

interface MovieDetailsProps {
  movie: {
    id: string;
    title: string;
    description: string;
    year: string;
    rating: string;
    duration: string;
    genres: Genre[];
    cast: Cast[];
    director: string;
    country: string;
    language: string;
    poster_url: string;
    backdrop_url?: string;
    views: number;
    likes: number;
    download_count: number;
  };
  onPlay: () => void;
  onDownload?: () => void;
  onLike?: () => void;
  onShare?: () => void;
  className?: string;
}

const MovieDetails: React.FC<MovieDetailsProps> = ({
  movie,
  onPlay,
  onDownload,
  onLike,
  onShare,
  className = ''
}) => {
  const [isDescriptionExpanded, setIsDescriptionExpanded] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'cast' | 'details'>('overview');

  const formatNumber = (num: number) => {
    if (num >= 1000000) {
      return `${(num / 1000000).toFixed(1)}M`;
    }
    if (num >= 1000) {
      return `${(num / 1000).toFixed(1)}K`;
    }
    return num.toString();
  };

  const truncateDescription = (text: string, maxLength: number) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  };

  return (
    <div className={`movie-details ${className}`}>
      {/* Movie Header */}
      <div className="movie-header">
        <div className="movie-poster">
          <img 
            src={movie.poster_url} 
            alt={movie.title}
            className="poster-image"
          />
          <div className="poster-overlay">
            <button className="play-overlay-btn" onClick={onPlay}>
              <Play size={24} />
            </button>
          </div>
        </div>

        <div className="movie-info">
          <h1 className="movie-title">{movie.title}</h1>
          
          <div className="movie-meta">
            <div className="meta-item">
              <Calendar size={16} />
              <span>{movie.year}</span>
            </div>
            <div className="meta-item">
              <Clock size={16} />
              <span>{movie.duration}</span>
            </div>
            <div className="meta-item">
              <Star size={16} className="star-icon" />
              <span>{movie.rating}/10</span>
            </div>
            <div className="meta-item">
              <Globe size={16} />
              <span>{movie.country}</span>
            </div>
          </div>

          <div className="movie-genres">
            {movie.genres.map(genre => (
              <span key={genre.id} className="genre-tag">
                {genre.name}
              </span>
            ))}
          </div>

          <div className="movie-stats">
            <div className="stat-item">
              <Users size={16} />
              <span>{formatNumber(movie.views)} views</span>
            </div>
            <div className="stat-item">
              <Heart size={16} />
              <span>{formatNumber(movie.likes)} likes</span>
            </div>
            <div className="stat-item">
              <Download size={16} />
              <span>{formatNumber(movie.download_count)} downloads</span>
            </div>
          </div>

          <div className="action-buttons">
            <button className="primary-btn" onClick={onPlay}>
              <Play size={20} />
              Watch Now
            </button>
            {onDownload && (
              <button className="secondary-btn" onClick={onDownload}>
                <Download size={20} />
                Download
              </button>
            )}
            {onLike && (
              <button className="secondary-btn" onClick={onLike}>
                <Heart size={20} />
                Like
              </button>
            )}
            {onShare && (
              <button className="secondary-btn" onClick={onShare}>
                <Share2 size={20} />
                Share
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Content Tabs */}
      <div className="movie-tabs">
        <div className="tab-headers">
          <button 
            className={`tab-header ${activeTab === 'overview' ? 'active' : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            Overview
          </button>
          <button 
            className={`tab-header ${activeTab === 'cast' ? 'active' : ''}`}
            onClick={() => setActiveTab('cast')}
          >
            Cast & Crew
          </button>
          <button 
            className={`tab-header ${activeTab === 'details' ? 'active' : ''}`}
            onClick={() => setActiveTab('details')}
          >
            Details
          </button>
        </div>

        <div className="tab-content">
          {activeTab === 'overview' && (
            <div className="overview-tab">
              <div className="description-section">
                <h3>Synopsis</h3>
                <p className="movie-description">
                  {isDescriptionExpanded 
                    ? movie.description 
                    : truncateDescription(movie.description, 200)
                  }
                  {movie.description.length > 200 && (
                    <button 
                      className="expand-btn"
                      onClick={() => setIsDescriptionExpanded(!isDescriptionExpanded)}
                    >
                      {isDescriptionExpanded ? ' Show Less' : ' Read More'}
                    </button>
                  )}
                </p>
              </div>

              <div className="director-section">
                <h3>Director</h3>
                <p className="director-name">{movie.director}</p>
              </div>
            </div>
          )}

          {activeTab === 'cast' && (
            <div className="cast-tab">
              <h3>Cast</h3>
              <div className="cast-grid">
                {movie.cast.map(actor => (
                  <div key={actor.id} className="cast-item">
                    <div className="cast-photo">
                      {actor.profile_path ? (
                        <img 
                          src={actor.profile_path} 
                          alt={actor.name}
                          className="cast-image"
                        />
                      ) : (
                        <div className="cast-placeholder">
                          <Users size={24} />
                        </div>
                      )}
                    </div>
                    <div className="cast-info">
                      <div className="cast-name">{actor.name}</div>
                      <div className="cast-character">{actor.character}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'details' && (
            <div className="details-tab">
              <h3>Movie Information</h3>
              <div className="details-grid">
                <div className="detail-item">
                  <span className="detail-label">Title:</span>
                  <span className="detail-value">{movie.title}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Year:</span>
                  <span className="detail-value">{movie.year}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Duration:</span>
                  <span className="detail-value">{movie.duration}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Rating:</span>
                  <span className="detail-value">{movie.rating}/10</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Language:</span>
                  <span className="detail-value">{movie.language}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Country:</span>
                  <span className="detail-value">{movie.country}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Director:</span>
                  <span className="detail-value">{movie.director}</span>
                </div>
                <div className="detail-item">
                  <span className="detail-label">Genres:</span>
                  <span className="detail-value">
                    {movie.genres.map(g => g.name).join(', ')}
                  </span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default MovieDetails;