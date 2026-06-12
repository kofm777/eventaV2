import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  RegisterParticipantRequest,
  RegisterResponse,
  ParticipantsResponse,
  Participant
} from '../models/participant.model';
import {
  LoginRequest,
  LoginResponse,
  ScanRequest,
  ScanResponse
} from '../models/admin.model';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) { }

  private getHeaders(): HttpHeaders {
    const token = localStorage.getItem('admin_token');
    let headers = new HttpHeaders({
      'Content-Type': 'application/json'
    });

    if (token) {
      headers = headers.set('Authorization', `Bearer ${token}`);
    }

    return headers;
  }

  // Public endpoints
  register(data: RegisterParticipantRequest): Observable<RegisterResponse> {
    return this.http.post<RegisterResponse>(`${this.apiUrl}/register`, data);
  }

  scan(data: { payload?: string; qr_image?: string; scanner_user?: string }): Observable<ScanResponse> {
    return this.http.post<ScanResponse>(`${this.apiUrl}/scan`, data);
  }

  // Auth endpoints
  login(credentials: LoginRequest): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/auth/admin/login`, credentials);
  }
  // Add these methods
  scanFair(data: { payload?: string; qr_image?: string; scanner_user?: string }): Observable<ScanResponse> {
    return this.http.post<ScanResponse>(`${this.apiUrl}/scan-fair`, data, {
      headers: this.getHeaders(),
      withCredentials: true
    });
  }

  scanConference(data: { payload?: string; qr_image?: string; scanner_user?: string }): Observable<ScanResponse> {
    return this.http.post<ScanResponse>(`${this.apiUrl}/scan-conference`, data, {
      headers: this.getHeaders(),
      withCredentials: true
    });
  }
  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/auth/logout`, {}, {
      headers: this.getHeaders()
    });
  }

  getMe(): Observable<any> {
    return this.http.get(`${this.apiUrl}/auth/me`, {
      headers: this.getHeaders()
    });
  }

  // Admin endpoints
  getParticipants(filters?: {
    status?: string;
    access_type?: string;
    search?: string;
    page?: number;
  }): Observable<ParticipantsResponse> {
    let params = new HttpParams();

    if (filters) {
      if (filters.status) params = params.set('status', filters.status);
      if (filters.access_type) params = params.set('access_type', filters.access_type);
      if (filters.search) params = params.set('search', filters.search);
      if (filters.page) params = params.set('page', filters.page.toString());
    }

    return this.http.get<ParticipantsResponse>(`${this.apiUrl}/admin/participants`, {
      headers: this.getHeaders(),
      params
    });
  }

  acceptParticipant(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/participants/${id}/accept`, {}, {
      headers: this.getHeaders()
    });
  }

  rejectParticipant(id: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/participants/${id}/reject`, {}, {
      headers: this.getHeaders()
    });
  }

  deleteParticipant(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/admin/participants/${id}`, {
      headers: this.getHeaders()
    });
  }

  downloadBadge(id: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/admin/participants/${id}/badge`, {
      headers: this.getHeaders(),
      responseType: 'blob'
    });
  }

  getRecentScans(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/scans`, {
      headers: this.getHeaders()
    });
  }

  getDashboardStats(): Observable<any> {
    return this.http.get(`${this.apiUrl}/admin/dashboard`, {
      headers: this.getHeaders()
    });
  }

  // Health check
  healthCheck(): Observable<any> {
    return this.http.get(`${environment.apiBaseUrl}/api/health`);
  }
}
