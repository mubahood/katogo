import React, { useState, useRef, useEffect } from 'react';
import { Play, Pause, Volume2, VolumeX, RotateCcw, RotateCw, Maximize, Settings, X, Lock, Unlock } from 'lucide-react';
import './VideoPlayer.css';

interface VideoPlayerProps {
  src: string;
  poster?: string;
  movieTitle: string;
  movieId: string;
  startTime?: number;
  onTimeUpdate?: (currentTime: number) => void;
  onFullExit?: () => void;
}

interface PlaybackState {
  isPlaying: boolean;
  currentTime: number;
  duration: number;
  volume: number;
  playbackRate: number;
  isMuted: boolean;
  isFullscreen: boolean;
  isLoading: boolean;
  isControlsVisible: boolean;
  isLocked: boolean;
  showSettings: boolean;
}

const VideoPlayer: React.FC<VideoPlayerProps> = ({
  src,
  poster,
  movieTitle,
  movieId,
  startTime = 0,
  onTimeUpdate,
  onFullExit
}) => {
  const videoRef = useRef<HTMLVideoElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const controlsTimeoutRef = useRef<NodeJS.Timeout>();
  const progressUploadRef = useRef<NodeJS.Timeout>();

  const [state, setState] = useState<PlaybackState>({
    isPlaying: false,
    currentTime: 0,
    duration: 0,
    volume: 1,
    playbackRate: 1,
    isMuted: false,
    isFullscreen: false,
    isLoading: true,
    isControlsVisible: true,
    isLocked: false,
    showSettings: false
  });

  // Initialize video
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const handleLoadedMetadata = () => {
      setState(prev => ({ 
        ...prev, 
        duration: video.duration,
        isLoading: false 
      }));
      
      // Resume from last position
      if (startTime > 0) {
        video.currentTime = startTime;
      }
    };

    const handleTimeUpdate = () => {
      setState(prev => ({ 
        ...prev, 
        currentTime: video.currentTime 
      }));
      
      // Call parent callback
      onTimeUpdate?.(video.currentTime);
    };

    const handleLoadStart = () => {
      setState(prev => ({ ...prev, isLoading: true }));
    };

    const handleCanPlay = () => {
      setState(prev => ({ ...prev, isLoading: false }));
    };

    video.addEventListener('loadedmetadata', handleLoadedMetadata);
    video.addEventListener('timeupdate', handleTimeUpdate);
    video.addEventListener('loadstart', handleLoadStart);
    video.addEventListener('canplay', handleCanPlay);

    return () => {
      video.removeEventListener('loadedmetadata', handleLoadedMetadata);
      video.removeEventListener('timeupdate', handleTimeUpdate);
      video.removeEventListener('loadstart', handleLoadStart);
      video.removeEventListener('canplay', handleCanPlay);
    };
  }, [startTime, onTimeUpdate]);

  // Auto-hide controls
  useEffect(() => {
    if (state.isControlsVisible && state.isPlaying && !state.showSettings && !state.isLocked) {
      controlsTimeoutRef.current = setTimeout(() => {
        setState(prev => ({ ...prev, isControlsVisible: false }));
      }, 5000);
    }

    return () => {
      if (controlsTimeoutRef.current) {
        clearTimeout(controlsTimeoutRef.current);
      }
    };
  }, [state.isControlsVisible, state.isPlaying, state.showSettings, state.isLocked]);

  // Upload progress every 5 seconds
  useEffect(() => {
    if (state.isPlaying && state.currentTime > 0) {
      progressUploadRef.current = setTimeout(() => {
        uploadProgress(movieId, state.currentTime);
      }, 5000);
    }

    return () => {
      if (progressUploadRef.current) {
        clearTimeout(progressUploadRef.current);
      }
    };
  }, [state.isPlaying, state.currentTime, movieId]);

  // Fullscreen handling
  useEffect(() => {
    const handleFullscreenChange = () => {
      const isFullscreen = !!document.fullscreenElement;
      setState(prev => ({ ...prev, isFullscreen }));
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  const uploadProgress = async (movieId: string, currentTime: number) => {
    try {
      await fetch('/api/movies/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          movie_id: movieId, 
          progress: currentTime 
        })
      });
    } catch (error) {
      console.error('Failed to upload progress:', error);
    }
  };

  const togglePlay = () => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    if (state.isPlaying) {
      video.pause();
    } else {
      video.play();
    }
    setState(prev => ({ ...prev, isPlaying: !prev.isPlaying }));
  };

  const skipTime = (seconds: number) => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    video.currentTime = Math.max(0, Math.min(video.currentTime + seconds, video.duration));
  };

  const handleProgressChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    const newTime = (parseFloat(e.target.value) / 100) * video.duration;
    video.currentTime = newTime;
  };

  const toggleMute = () => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    video.muted = !video.muted;
    setState(prev => ({ ...prev, isMuted: !prev.isMuted }));
  };

  const handleVolumeChange = (volume: number) => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    video.volume = volume;
    video.muted = volume === 0;
    setState(prev => ({ 
      ...prev, 
      volume, 
      isMuted: volume === 0 
    }));
  };

  const handlePlaybackRateChange = (rate: number) => {
    const video = videoRef.current;
    if (!video || state.isLocked) return;

    video.playbackRate = rate;
    setState(prev => ({ ...prev, playbackRate: rate }));
  };

  const toggleFullscreen = async () => {
    const container = containerRef.current;
    if (!container) return;

    try {
      if (!document.fullscreenElement) {
        await container.requestFullscreen();
      } else {
        await document.exitFullscreen();
      }
    } catch (error) {
      console.error('Fullscreen error:', error);
    }
  };

  const toggleControls = () => {
    if (state.isLocked) return;
    setState(prev => ({ ...prev, isControlsVisible: !prev.isControlsVisible }));
  };

  const toggleLock = () => {
    setState(prev => ({ 
      ...prev, 
      isLocked: !prev.isLocked,
      showSettings: false 
    }));
  };

  const handleExit = () => {
    if (state.isFullscreen) {
      document.exitFullscreen();
    }
    onFullExit?.();
  };

  const formatTime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
      return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
  };

  const progressPercentage = state.duration ? (state.currentTime / state.duration) * 100 : 0;

  return (
    <div 
      ref={containerRef}
      className={`video-player ${state.isFullscreen ? 'fullscreen' : ''}`}
      onClick={toggleControls}
    >
      {/* Video Element */}
      <video
        ref={videoRef}
        src={src}
        poster={poster}
        className="video-element"
        playsInline
      />

      {/* Loading State */}
      {state.isLoading && (
        <div className="video-loading">
          <div className="loading-spinner"></div>
          <div className="loading-text">Loading...</div>
        </div>
      )}

      {/* Video Controls Overlay */}
      <div className={`video-overlay ${state.isControlsVisible ? 'visible' : ''}`}>
        {/* Header */}
        <div className="video-header">
          <button 
            className="exit-btn"
            onClick={handleExit}
            disabled={state.isLocked}
          >
            <X size={24} />
          </button>
          <div className="video-title">{movieTitle}</div>
          <button 
            className="lock-btn"
            onClick={toggleLock}
          >
            {state.isLocked ? <Lock size={20} /> : <Unlock size={20} />}
          </button>
        </div>

        {/* Center Controls */}
        <div className="video-center">
          <button 
            className="skip-btn"
            onClick={() => skipTime(-10)}
            disabled={state.isLocked}
          >
            <RotateCcw size={32} />
            <span>10</span>
          </button>
          
          <button 
            className="play-btn"
            onClick={togglePlay}
            disabled={state.isLocked}
          >
            {state.isPlaying ? <Pause size={48} /> : <Play size={48} />}
          </button>
          
          <button 
            className="skip-btn"
            onClick={() => skipTime(10)}
            disabled={state.isLocked}
          >
            <RotateCw size={32} />
            <span>10</span>
          </button>
        </div>

        {/* Bottom Controls */}
        <div className="video-bottom">
          {/* Progress Bar */}
          <div className="progress-container">
            <input
              type="range"
              className="progress-bar"
              min="0"
              max="100"
              value={progressPercentage}
              onChange={handleProgressChange}
              disabled={state.isLocked}
            />
          </div>

          {/* Control Bar */}
          <div className="control-bar">
            <div className="control-left">
              <span className="time-display">
                {formatTime(state.currentTime)} / {formatTime(state.duration)}
              </span>
            </div>

            <div className="control-right">
              <button 
                className="control-btn"
                onClick={toggleMute}
                disabled={state.isLocked}
              >
                {state.isMuted ? <VolumeX size={20} /> : <Volume2 size={20} />}
              </button>
              
              <button 
                className="control-btn"
                onClick={() => setState(prev => ({ ...prev, showSettings: !prev.showSettings }))}
                disabled={state.isLocked}
              >
                <Settings size={20} />
              </button>
              
              <button 
                className="control-btn"
                onClick={toggleFullscreen}
                disabled={state.isLocked}
              >
                <Maximize size={20} />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Settings Panel */}
      {state.showSettings && !state.isLocked && (
        <div className="settings-panel">
          <div className="settings-content">
            <h3>Playback Settings</h3>
            
            <div className="settings-section">
              <label>Playback Speed</label>
              <div className="settings-options">
                {[0.5, 1, 1.25, 1.5, 2].map(rate => (
                  <button
                    key={rate}
                    className={`settings-option ${state.playbackRate === rate ? 'active' : ''}`}
                    onClick={() => handlePlaybackRateChange(rate)}
                  >
                    {rate}x
                  </button>
                ))}
              </div>
            </div>

            <div className="settings-section">
              <label>Volume</label>
              <div className="settings-options">
                {[0, 0.5, 1].map(volume => (
                  <button
                    key={volume}
                    className={`settings-option ${state.volume === volume ? 'active' : ''}`}
                    onClick={() => handleVolumeChange(volume)}
                  >
                    {volume === 0 ? 'Mute' : `${Math.round(volume * 100)}%`}
                  </button>
                ))}
              </div>
            </div>

            <button 
              className="settings-close"
              onClick={() => setState(prev => ({ ...prev, showSettings: false }))}
            >
              Close
            </button>
          </div>
        </div>
      )}

      {/* Lock Indicator */}
      {state.isLocked && (
        <div className="lock-indicator">
          <Lock size={24} />
          <span>Controls Locked</span>
        </div>
      )}
    </div>
  );
};

export default VideoPlayer;