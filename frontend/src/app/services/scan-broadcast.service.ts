import { Injectable } from '@angular/core';
import { Observable, Subject } from 'rxjs';

export interface ScanResult {
    participant: any;
    is_already_scanned: boolean;
    message: string;
    scanType: 'fair' | 'conference';
}

@Injectable({ providedIn: 'root' })
export class ScanBroadcastService {
    private fairChannel = new BroadcastChannel('fair_scan');
    private confChannel = new BroadcastChannel('conference_scan');

    private fairSubject = new Subject<ScanResult>();
    private confSubject = new Subject<ScanResult>();

    constructor() {
        this.fairChannel.onmessage = (e) => this.fairSubject.next(e.data);
        this.confChannel.onmessage = (e) => this.confSubject.next(e.data);
    }

    getFairScan$(): Observable<ScanResult> {
        return this.fairSubject.asObservable();
    }

    getConferenceScan$(): Observable<ScanResult> {
        return this.confSubject.asObservable();
    }

    broadcastFair(result: ScanResult) {
        this.fairChannel.postMessage(result);
    }

    broadcastConference(result: ScanResult) {
        this.confChannel.postMessage(result);
    }
}