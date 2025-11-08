export interface Admin {
  id: number;
  name: string;
  email: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  ok: boolean;
  admin: Admin;
  token: string;
  message: string;
}

export interface ScanRequest {
  payload: string;
  scanner_user?: string;
}

export interface ScanResponse {
  ok: boolean;
  participant?: any;
  scan_id?: number;
  message: string;
}
