// src/app/pages/avatar/avatar-page.component.ts
import { Component, OnInit, AfterViewInit, OnDestroy, ViewChild, ElementRef, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ScanBroadcastService, ScanResult } from '../../services/scan-broadcast.service';

@Component({
  selector: 'app-avatar-page',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './avatar-page.component.html',
  styleUrls: ['./avatar-page.component.css']
})
export class AvatarPageComponent implements OnInit, AfterViewInit, OnDestroy {
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
    this.sub = this.broadcaster.getFairScan$().subscribe(scan => {
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
    // Auto-unlock audio for kiosk mode
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
        this.accessStatus = this.alreadyScanned ? 'Denied' : 'Accepted';
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

  private playWelcomeMessage() {
    if (this.isSpeaking) return;

    let msg = '';
    if (!this.participantName) {
      msg = "Scan not recognized.";
    } else if (this.alreadyScanned) {
      // ✅ Scenarios: re-scan fair (1st or after conference)
      msg = `Dear ${this.honorific()}, ${this.participantName}, fair access already granted.`;
    } else {
      // ✅ Scenarios: first-time fair scan
      msg = `Dear ${this.honorific()}, ${this.participantName}, welcome to the fair. Enjoy your event!`;
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