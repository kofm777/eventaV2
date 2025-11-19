// src/app/services/scan-broadcast.service.ts
import { Injectable } from '@angular/core';
import { Observable, Subject } from 'rxjs';

/**
 * Represents a scan result broadcasted to avatar pages.
 */
export interface ScanResult {
    participant: {
        id: number;
        first_name: string;
        last_name: string;
        gender: string;
        access_type: 'fair' | 'fair + conference';
        status: string;
        scanned_fair: boolean;
        scanned_conference: boolean;
    } | null;
    is_already_scanned: boolean;
    message: string;
    scanType: 'fair' | 'conference';
}

@Injectable({
    providedIn: 'root'
})
export class ScanBroadcastService {
    // ✅ Isolated BroadcastChannel for fair scans
    private fairChannel = new BroadcastChannel('eventaccess_fair_scan');
    // ✅ Isolated BroadcastChannel for conference scans
    private confChannel = new BroadcastChannel('eventaccess_conference_scan');

    private fairSubject = new Subject<ScanResult>();
    private confSubject = new Subject<ScanResult>();

    constructor() {
        // Listen for fair scan messages
        this.fairChannel.onmessage = (event: MessageEvent) => {
            if (event.data && typeof event.data === 'object') {
                this.fairSubject.next(event.data as ScanResult);
            }
        };

        // Listen for conference scan messages
        this.confChannel.onmessage = (event: MessageEvent) => {
            if (event.data && typeof event.data === 'object') {
                this.confSubject.next(event.data as ScanResult);
            }
        };
    }

    /**
     * Get Observable for fair scan results.
     */
    getFairScan$(): Observable<ScanResult> {
        return this.fairSubject.asObservable();
    }

    /**
     * Get Observable for conference scan results.
     */
    getConferenceScan$(): Observable<ScanResult> {
        return this.confSubject.asObservable();
    }

    /**
     * Broadcast a fair scan result to all fair avatar tabs.
     */
    broadcastFair(result: ScanResult): void {
        // Enforce scanType
        const payload = { ...result, scanType: 'fair' as const };
        this.fairChannel.postMessage(payload);
    }

    /**
     * Broadcast a conference scan result to all conference avatar tabs.
     */
    broadcastConference(result: ScanResult): void {
        // Enforce scanType
        const payload = { ...result, scanType: 'conference' as const };
        this.confChannel.postMessage(payload);
    }

    /**
     * Close all BroadcastChannels (optional, for cleanup)
     */
    destroy(): void {
        this.fairChannel.close();
        this.confChannel.close();
    }
}