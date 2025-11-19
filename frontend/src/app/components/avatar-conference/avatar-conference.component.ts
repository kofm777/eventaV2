// src/app/components/avatar-conference/avatar-conference.component.ts
import { Component, OnInit, AfterViewInit, OnDestroy, ViewChild, ElementRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ScanBroadcastService, ScanResult } from '../../services/scan-broadcast.service';

@Component({
    selector: 'app-avatar-conference',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './avatar-conference.component.html',
    styleUrls: ['./avatar-conference.component.css']
})
export class AvatarConferenceComponent implements OnInit, AfterViewInit, OnDestroy {
    @ViewChild('silentVideo') silentVideo!: ElementRef<HTMLVideoElement>;
    @ViewChild('speakingVideo') speakingVideo!: ElementRef<HTMLVideoElement>;

    participantName = '';
    gender = 'Female';
    accessStatus = 'Accepted';
    accessType = 'fair + conference';
    alreadyScanned = false;
    isSpeaking = false;
    error: string | null = null;

    private sub: any;

    constructor(
        private broadcaster: ScanBroadcastService,
        private ngZone: NgZone
    ) {}

// Add this field
    isSystemStarted = false;

// Replace ngOnInit with:
    ngOnInit() {}

// Add this method
    startSystem() {
        this.isSystemStarted = true;
        this.playSilentVideo(); // Ensure video is ready
    }

    ngAfterViewInit() {
        this.playSilentVideo();
        this.sub = this.broadcaster.getConferenceScan$().subscribe(scan => {
            this.handleScan(scan);
        });
    }

    ngOnDestroy() {
        if (this.sub) this.sub.unsubscribe();
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    }

    private unlockAudio() {
        // Auto-unlock for kiosk mode
        try {
            const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
            const buffer = ctx.createBuffer(1, 1, 22050);
            const source = ctx.createBufferSource();
            source.buffer = buffer;
            source.connect(ctx.destination);
            source.start(0);
        } catch (e) {
            console.warn('Web Audio unlock failed');
        }

        try {
            const empty = new SpeechSynthesisUtterance('');
            speechSynthesis.speak(empty);
        } catch (e) {
            console.warn('TTS unlock failed');
        }
    }

    private handleScan(scan: ScanResult) {
        const p = scan.participant;
        this.ngZone.run(() => {
            if (!p || !p.first_name) {
                this.participantName = '';
                this.accessStatus = 'Denied';
                this.error = 'Participant not found.';
            } else {
                this.participantName = `${p.first_name} ${p.last_name}`.trim();
                this.gender = p.gender || 'Female';
                this.accessType = p.access_type || 'fair';
                this.alreadyScanned = scan.is_already_scanned;
                this.accessStatus = this.determineAccessStatus();
                this.error = null;
            }
            // ✅ Only speak if system is started
            if (this.isSystemStarted) {
                setTimeout(() => this.playWelcomeMessage(), 50);
            }
        });

        // Fix NG0100
       // setTimeout(() => this.playWelcomeMessage(), 50);
    }

    private determineAccessStatus(): string {
        if (this.accessType === 'fair') {
            return 'Denied'; // Fair-only user at conference
        }
        return this.alreadyScanned ? 'Denied' : 'Accepted';
    }

    private playWelcomeMessage() {
        if (this.isSpeaking) return;

        let msg = '';
        if (!this.participantName) {
            msg = "Scan not recognized.";
        } else if (this.accessType === 'fair') {
            // ✅ Scenario 6: Fair-only user at conference
            msg = `Dear ${this.honorific()}, ${this.participantName}, conference access is not permitted. You have fair-only access.`;
        } else if (this.alreadyScanned) {
            // ✅ Scenarios 4 & 5: Re-scan
            msg = `Dear ${this.honorific()}, ${this.participantName}, conference access has already been granted.`;
        } else {
            // ✅ Scenario 3: First-time conference scan
            msg = `Dear ${this.honorific()}, ${this.participantName}, welcome to the conference. Enjoy your event`;
        }

        this.speak(msg);
    }

    private honorific(): string {
        return this.gender === 'Male' ? 'Mr.' : 'Ms.';
    }

    private speak(text: string) {
        this.switchToSpeakingAvatar();

        const u = new SpeechSynthesisUtterance(text);
        u.lang = 'en-US';
        u.rate = 0.95;

        // ✅ Force female voice
        const voices = speechSynthesis.getVoices();
        const female = voices.find(v =>
            v.lang.startsWith('en') &&
            (v.name.toLowerCase().includes('samantha') || v.name.toLowerCase().includes('zira'))
        );
        if (female) u.voice = female;

        u.onstart = () => this.ngZone.run(() => this.isSpeaking = true);
        u.onend = () => this.ngZone.run(() => {
            this.isSpeaking = false;
            this.switchToSilentAvatar();
        });
        u.onerror = () => this.ngZone.run(() => {
            this.isSpeaking = false;
            this.switchToSilentAvatar();
        });

        speechSynthesis.speak(u);
    }

    private switchToSpeakingAvatar() {
        const silent = this.silentVideo?.nativeElement;
        const speaking = this.speakingVideo?.nativeElement;
        if (silent) silent.pause();
        if (speaking) {
            speaking.currentTime = 0;
            speaking.muted = true;
            speaking.loop = true;
            speaking.play().catch(e => console.warn('Speaking video play failed:', e));
        }
    }

    private switchToSilentAvatar() {
        const speaking = this.speakingVideo?.nativeElement;
        const silent = this.silentVideo?.nativeElement;
        if (speaking) speaking.pause();
        if (silent) {
            silent.muted = true;
            silent.loop = true;
            silent.play().catch(e => console.warn('Silent video play failed:', e));
        }
    }

    private playSilentVideo() {
        const silent = this.silentVideo?.nativeElement;
        if (silent) {
            silent.muted = true;
            silent.loop = true;
            silent.play().catch(e => console.warn('Initial silent play failed:', e));
        }
    }

    getAccessTypeDisplay(): string {
        switch (this.accessType) {
            case 'fair': return 'Fair only';
            case 'fair + conference': return 'Fair and Conference';
            default: return '';
        }
    }

    onVideoError() {
        this.error = 'Video error.';
    }
}