import React, { useState } from 'react';
import { ApiService } from '../../services/ApiService';

interface ProfileTabProps {
  user: {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    member_since: string;
  } | undefined;
  onUpdate: () => void;
}

const ProfileTab: React.FC<ProfileTabProps> = ({ user, onUpdate }) => {
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    current_password: '',
    new_password: '',
    confirm_password: ''
  });
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{type: 'success' | 'error', text: string} | null>(null);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value
    }));
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      setMessage(null);

      // Validate password fields if changing password
      if (formData.new_password) {
        if (formData.new_password !== formData.confirm_password) {
          setMessage({type: 'error', text: 'New passwords do not match'});
          return;
        }
        if (formData.new_password.length < 6) {
          setMessage({type: 'error', text: 'Password must be at least 6 characters'});
          return;
        }
        if (!formData.current_password) {
          setMessage({type: 'error', text: 'Current password is required to change password'});
          return;
        }
      }

      const updateData: any = {
        name: formData.name,
        email: formData.email
      };

      if (formData.new_password) {
        updateData.current_password = formData.current_password;
        updateData.new_password = formData.new_password;
      }

      const response = await ApiService.post('/me', updateData);
      if (response.data?.success) {
        setMessage({type: 'success', text: 'Profile updated successfully'});
        setEditing(false);
        setFormData(prev => ({
          ...prev,
          current_password: '',
          new_password: '',
          confirm_password: ''
        }));
        onUpdate();
      } else {
        setMessage({type: 'error', text: 'Failed to update profile'});
      }
    } catch (err: any) {
      setMessage({type: 'error', text: err.message || 'Failed to update profile'});
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    setEditing(false);
    setFormData({
      name: user?.name || '',
      email: user?.email || '',
      current_password: '',
      new_password: '',
      confirm_password: ''
    });
    setMessage(null);
  };

  const formatMemberSince = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  if (!user) {
    return (
      <div className="profile-loading">
        <div className="loading-spinner"></div>
        <p>Loading profile...</p>
      </div>
    );
  }

  return (
    <div className="profile-tab">
      <div className="profile-header">
        <h2 className="profile-title">
          <span className="title-icon">👤</span>
          My Profile
        </h2>
        {!editing && (
          <button 
            className="edit-button"
            onClick={() => setEditing(true)}
          >
            <span className="edit-icon">✏️</span>
            Edit Profile
          </button>
        )}
      </div>

      {message && (
        <div className={`message ${message.type}`}>
          {message.type === 'success' ? '✅' : '❌'} {message.text}
        </div>
      )}

      <div className="profile-content">
        {/* Avatar Section */}
        <div className="avatar-section">
          <div className="avatar-container">
            {user.avatar ? (
              <img 
                src={user.avatar} 
                alt={user.name}
                className="avatar-large"
              />
            ) : (
              <div className="avatar-placeholder-large">
                {user.name.charAt(0).toUpperCase()}
              </div>
            )}
            <button className="avatar-edit-button" title="Change Avatar">
              📷
            </button>
          </div>
          <div className="avatar-info">
            <h3>{user.name}</h3>
            <p>Member since {formatMemberSince(user.member_since)}</p>
          </div>
        </div>

        {/* Profile Form */}
        <div className="profile-form">
          <h4 className="form-section-title">
            <span className="section-icon">📝</span>
            Account Information
          </h4>
          
          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="name">Full Name</label>
              {editing ? (
                <input
                  type="text"
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleInputChange}
                  className="form-input"
                  placeholder="Enter your full name"
                />
              ) : (
                <div className="form-display">{user.name}</div>
              )}
            </div>

            <div className="form-group">
              <label htmlFor="email">Email Address</label>
              {editing ? (
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  className="form-input"
                  placeholder="Enter your email"
                />
              ) : (
                <div className="form-display">{user.email}</div>
              )}
            </div>
          </div>

          {editing && (
            <>
              <h4 className="form-section-title">
                <span className="section-icon">🔒</span>
                Change Password
                <span className="optional-label">(Optional)</span>
              </h4>
              
              <div className="form-grid">
                <div className="form-group">
                  <label htmlFor="current_password">Current Password</label>
                  <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    value={formData.current_password}
                    onChange={handleInputChange}
                    className="form-input"
                    placeholder="Enter current password"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="new_password">New Password</label>
                  <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    value={formData.new_password}
                    onChange={handleInputChange}
                    className="form-input"
                    placeholder="Enter new password"
                  />
                </div>

                <div className="form-group">
                  <label htmlFor="confirm_password">Confirm New Password</label>
                  <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    value={formData.confirm_password}
                    onChange={handleInputChange}
                    className="form-input"
                    placeholder="Confirm new password"
                  />
                </div>
              </div>
            </>
          )}

          {editing && (
            <div className="form-actions">
              <button 
                className="save-button"
                onClick={handleSave}
                disabled={saving}
              >
                {saving ? '💾 Saving...' : '💾 Save Changes'}
              </button>
              <button 
                className="cancel-button"
                onClick={handleCancel}
                disabled={saving}
              >
                ❌ Cancel
              </button>
            </div>
          )}
        </div>

        {/* Account Stats */}
        <div className="account-stats">
          <h4 className="form-section-title">
            <span className="section-icon">📊</span>
            Account Statistics
          </h4>
          
          <div className="stats-grid">
            <div className="stat-item">
              <div className="stat-label">User ID</div>
              <div className="stat-value">#{user.id}</div>
            </div>
            
            <div className="stat-item">
              <div className="stat-label">Account Status</div>
              <div className="stat-value status-active">✅ Active</div>
            </div>
            
            <div className="stat-item">
              <div className="stat-label">Account Type</div>
              <div className="stat-value">🌟 Premium Member</div>
            </div>
            
            <div className="stat-item">
              <div className="stat-label">Last Login</div>
              <div className="stat-value">Just now</div>
            </div>
          </div>
        </div>
      </div>

      <style jsx>{`
        .profile-tab {
          height: 100%;
          overflow-y: auto;
        }

        .profile-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-title {
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

        .edit-button {
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

        .edit-button:hover {
          transform: translateY(-2px);
        }

        .edit-icon {
          font-size: 1rem;
        }

        .message {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 8px;
          padding: 1rem;
          margin-bottom: 1.5rem;
          border-left: 4px solid;
        }

        .message.success {
          border-left-color: #4ade80;
          background: rgba(74, 222, 128, 0.1);
        }

        .message.error {
          border-left-color: #f87171;
          background: rgba(248, 113, 113, 0.1);
        }

        .profile-content {
          display: flex;
          flex-direction: column;
          gap: 2rem;
        }

        .avatar-section {
          display: flex;
          align-items: center;
          gap: 2rem;
          padding: 2rem;
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
        }

        .avatar-container {
          position: relative;
        }

        .avatar-large {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          object-fit: cover;
          border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-placeholder-large {
          width: 120px;
          height: 120px;
          border-radius: 50%;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 3rem;
          font-weight: bold;
          color: white;
          border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .avatar-edit-button {
          position: absolute;
          bottom: 0;
          right: 0;
          background: rgba(255, 255, 255, 0.9);
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          font-size: 1.2rem;
          transition: all 0.3s ease;
        }

        .avatar-edit-button:hover {
          background: white;
          transform: scale(1.1);
        }

        .avatar-info h3 {
          font-size: 1.75rem;
          font-weight: 700;
          margin: 0 0 0.5rem 0;
          color: #ffffff;
        }

        .avatar-info p {
          font-size: 1rem;
          color: rgba(255, 255, 255, 0.7);
          margin: 0;
        }

        .profile-form {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 2rem;
        }

        .form-section-title {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 1.25rem;
          font-weight: 600;
          margin: 0 0 1.5rem 0;
          color: #ffffff;
        }

        .section-icon {
          font-size: 1rem;
        }

        .optional-label {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          font-weight: 400;
          margin-left: 0.5rem;
        }

        .form-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }

        .form-group {
          display: flex;
          flex-direction: column;
          gap: 0.5rem;
        }

        .form-group label {
          font-weight: 600;
          color: rgba(255, 255, 255, 0.9);
          font-size: 0.9rem;
        }

        .form-input {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          border-radius: 8px;
          padding: 0.75rem;
          color: #ffffff;
          font-size: 1rem;
          transition: all 0.3s ease;
        }

        .form-input:focus {
          outline: none;
          border-color: rgba(102, 126, 234, 0.8);
          background: rgba(255, 255, 255, 0.15);
        }

        .form-input::placeholder {
          color: rgba(255, 255, 255, 0.5);
        }

        .form-display {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 0.75rem;
          color: #ffffff;
          font-size: 1rem;
        }

        .form-actions {
          display: flex;
          gap: 1rem;
          justify-content: flex-end;
          margin-top: 2rem;
          padding-top: 1.5rem;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .save-button {
          background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
          border: none;
          color: white;
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: transform 0.2s ease;
        }

        .save-button:hover:not(:disabled) {
          transform: translateY(-2px);
        }

        .save-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
          transform: none;
        }

        .cancel-button {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 255, 255, 0.2);
          color: rgba(255, 255, 255, 0.8);
          padding: 0.75rem 1.5rem;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          transition: all 0.2s ease;
        }

        .cancel-button:hover:not(:disabled) {
          background: rgba(255, 255, 255, 0.2);
          color: #ffffff;
        }

        .cancel-button:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }

        .account-stats {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 12px;
          padding: 2rem;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 1.5rem;
        }

        .stat-item {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 8px;
          padding: 1.5rem;
          text-align: center;
        }

        .stat-label {
          font-size: 0.85rem;
          color: rgba(255, 255, 255, 0.6);
          margin-bottom: 0.5rem;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .stat-value {
          font-size: 1.1rem;
          font-weight: 600;
          color: #ffffff;
        }

        .status-active {
          color: #4ade80 !important;
        }

        .profile-loading {
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

        /* Mobile Responsive */
        @media (max-width: 768px) {
          .profile-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
          }

          .profile-title {
            font-size: 1.5rem;
          }

          .avatar-section {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
          }

          .avatar-info h3 {
            font-size: 1.5rem;
          }

          .form-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
          }

          .form-actions {
            flex-direction: column;
            gap: 0.75rem;
          }

          .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
          }

          .stat-item {
            padding: 1rem;
          }
        }
      `}</style>
    </div>
  );
};

export default ProfileTab;