import axios, { AxiosResponse, AxiosRequestConfig } from 'axios';

export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  message?: string;
  error?: string;
}

class ApiServiceClass {
  private baseURL: string;
  private authToken: string | null = null;

  constructor() {
    this.baseURL = '/api';
    this.initializeAuth();
  }

  private initializeAuth() {
    // Get token from localStorage or cookie
    const token = localStorage.getItem('auth_token') || 
                  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
      this.setAuthToken(token);
    }
  }

  public setAuthToken(token: string) {
    this.authToken = token;
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  }

  public clearAuthToken() {
    this.authToken = null;
    delete axios.defaults.headers.common['Authorization'];
    localStorage.removeItem('auth_token');
  }

  private getConfig(config: AxiosRequestConfig = {}): AxiosRequestConfig {
    const defaultConfig: AxiosRequestConfig = {
      baseURL: this.baseURL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    };

    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
      defaultConfig.headers!['X-CSRF-TOKEN'] = csrfToken;
    }

    // Add auth token if available
    if (this.authToken) {
      defaultConfig.headers!['Authorization'] = `Bearer ${this.authToken}`;
    }

    return { ...defaultConfig, ...config };
  }

  public async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<T>>> {
    try {
      const response = await axios.get(url, this.getConfig(config));
      return response;
    } catch (error: any) {
      if (error.response?.status === 401) {
        this.handleUnauthorized();
      }
      throw this.handleError(error);
    }
  }

  public async post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<T>>> {
    try {
      const response = await axios.post(url, data, this.getConfig(config));
      return response;
    } catch (error: any) {
      if (error.response?.status === 401) {
        this.handleUnauthorized();
      }
      throw this.handleError(error);
    }
  }

  public async put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<T>>> {
    try {
      const response = await axios.put(url, data, this.getConfig(config));
      return response;
    } catch (error: any) {
      if (error.response?.status === 401) {
        this.handleUnauthorized();
      }
      throw this.handleError(error);
    }
  }

  public async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<T>>> {
    try {
      const response = await axios.delete(url, this.getConfig(config));
      return response;
    } catch (error: any) {
      if (error.response?.status === 401) {
        this.handleUnauthorized();
      }
      throw this.handleError(error);
    }
  }

  public async patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<ApiResponse<T>>> {
    try {
      const response = await axios.patch(url, data, this.getConfig(config));
      return response;
    } catch (error: any) {
      if (error.response?.status === 401) {
        this.handleUnauthorized();
      }
      throw this.handleError(error);
    }
  }

  private handleError(error: any): Error {
    if (error.response) {
      // Server responded with error status
      const message = error.response.data?.message || 
                     error.response.data?.error || 
                     `HTTP Error ${error.response.status}`;
      return new Error(message);
    } else if (error.request) {
      // Network error
      return new Error('Network error - please check your connection');
    } else {
      // Other error
      return new Error(error.message || 'An unexpected error occurred');
    }
  }

  private handleUnauthorized() {
    // Clear auth and redirect to login if needed
    this.clearAuthToken();
    
    // You can emit an event or call a callback here
    window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    
    // Optionally redirect to login page
    // window.location.href = '/login';
  }

  // Utility methods for common API patterns
  public async fetchPaginated<T = any>(url: string, page: number = 1, perPage: number = 20): Promise<AxiosResponse<ApiResponse<{
    items: T[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
  }>>> {
    return this.get(`${url}?page=${page}&per_page=${perPage}`);
  }

  public async uploadFile(url: string, file: File, onProgress?: (progress: number) => void): Promise<AxiosResponse<ApiResponse>> {
    const formData = new FormData();
    formData.append('file', file);

    const config: AxiosRequestConfig = {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          onProgress(progress);
        }
      },
    };

    return this.post(url, formData, config);
  }

  // Auth-specific methods
  public async login(email: string, password: string): Promise<AxiosResponse<ApiResponse<{ token: string; user: any }>>> {
    const response = await this.post('/auth/login', { email, password });
    
    if (response.data.success && response.data.data?.token) {
      this.setAuthToken(response.data.data.token);
      localStorage.setItem('auth_token', response.data.data.token);
    }
    
    return response;
  }

  public async logout(): Promise<void> {
    try {
      await this.post('/auth/logout');
    } catch (error) {
      // Ignore logout errors
    } finally {
      this.clearAuthToken();
    }
  }

  public async getCurrentUser(): Promise<AxiosResponse<ApiResponse<any>>> {
    return this.get('/me');
  }

  // Video-specific methods for your app
  public async saveVideoProgress(movieId: number, progress: number, duration?: number): Promise<AxiosResponse<ApiResponse>> {
    return this.post('/video-progress', {
      movie_id: movieId,
      progress,
      duration,
      device: navigator.userAgent,
      platform: 'web'
    });
  }

  public async getVideoProgress(movieId: number): Promise<AxiosResponse<ApiResponse<{ progress: number; duration?: number }>>> {
    return this.get(`/video-progress/${movieId}`);
  }

  public async getWatchHistory(page: number = 1, perPage: number = 20): Promise<AxiosResponse<ApiResponse>> {
    return this.fetchPaginated('/watch-history', page, perPage);
  }
}

// Export singleton instance
export const ApiService = new ApiServiceClass();

// Export the class for testing or multiple instances
export default ApiServiceClass;