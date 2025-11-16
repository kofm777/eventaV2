export interface Participant {
  id: number;
  first_name: string;
  last_name: string;
  company_name: string;
  gender: 'Male' | 'Female' | 'Other';
  phone?: string;
  email: string;
  access_type: 'fair' | 'fair + conference' | 'both';
  status: 'pending' | 'accepted' | 'rejected' | 'scanned';
  qr_token?: string;
  qr_payload?: any;
  qr_image?: string;
  created_at?: string;
  updated_at?: string;
}

export interface RegisterParticipantRequest {
  first_name: string;
  last_name: string;
  company_name: string;
  gender: 'Male' | 'Female' | 'Other';
  phone?: string;
  email: string;
  access_type: 'fair' | 'fair + conference' | 'both';
}

export interface RegisterResponse {
  ok: boolean;
  participant: Participant;
  qr: string;
  email_sent?: boolean;
  message: string;
}

export interface ParticipantsResponse {
  ok: boolean;
  participants: {
    data: Participant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
