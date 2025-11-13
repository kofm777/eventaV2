import {
  Component,
  Input,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  NgZone
} from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-speaker-avatar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './speaker-avatar.component.html',
  styleUrls: ['./speaker-avatar.component.css']
})
export class SpeakerAvatarComponent implements OnInit, OnDestroy {
  @ViewChild('silentVideo', { static: false }) silentVideo!: ElementRef<HTMLVideoElement>;
  @ViewChild('speakingVideo', { static: false }) speakingVideo!: ElementRef<HTMLVideoElement>;

  @Input() participantName: string = '';
  @Input() gender: string = 'Female';
  @Input() autoPlay: boolean = true;
  @Input() accessStatus: string = 'Accepted'; // 'Accepted' or 'Denied'
  @Input() accessType: string = 'fair + conference';

  private synth: SpeechSynthesis;
  private utterance: SpeechSynthesisUtterance | null = null;
  private voicesLoaded = false;
  private retryTimeout: any;

  isPlaying = false;
  isSpeaking = false;
  videoReady = false;
  error: string | null = null;

  private englishVoices: SpeechSynthesisVoice[] = [];
  private selectedVoice: SpeechSynthesisVoice | null = null;

  constructor(private ngZone: NgZone) {
    this.synth = window.speechSynthesis;
  }

  ngOnInit() {
    this.initVoices();

    // Start silent video
    if (this.silentVideo) {
      this.silentVideo.nativeElement.play().catch(e =>
          console.warn('Silent video play failed:', e)
      );
    }

    // ✅ AUTO-PLAY TTS IMMEDIATELY if data is ready
    if (this.autoPlay && this.participantName) {
      // Small delay ensures DOM/video are ready
      setTimeout(() => {
        this.playWelcomeMessage();
      }, 300);
    }

    // Auto-play TTS if autoPlay=true and data exists
    if (this.autoPlay && this.participantName) {
      // Wait for user interaction to avoid 'not-allowed' error
      const playAfterInteraction = () => {
        this.playWelcomeMessage();
        document.removeEventListener('click', playAfterInteraction);
        document.removeEventListener('keydown', playAfterInteraction);
      };
      document.addEventListener('click', playAfterInteraction);
      document.addEventListener('keydown', playAfterInteraction);
    }
  }

  ngOnDestroy() {
    this.stopSpeaking();
    if (this.retryTimeout) clearTimeout(this.retryTimeout);
  }

  private initVoices() {
    this.loadVoices();
    if (typeof speechSynthesis !== 'undefined') {
      speechSynthesis.onvoiceschanged = () => this.loadVoices();
    }
  }

  private loadVoices() {
    const voices = this.synth.getVoices();
    if (!voices.length) {
      setTimeout(() => this.loadVoices(), 300);
      return;
    }

    this.englishVoices = voices.filter(v => v.lang.toLowerCase().startsWith('en'));
    this.selectedVoice =
        this.englishVoices.find(v =>
            ['female', 'woman', 'zira', 'samantha', 'google us english'].some(k =>
                v.name.toLowerCase().includes(k)
            )
        ) || this.englishVoices.find(v => v.lang === 'en-US') || voices[0];

    this.voicesLoaded = true;
  }

  async playWelcomeMessage() {
    if (!this.participantName) return;

    if (!this.voicesLoaded) {
      await new Promise(resolve => setTimeout(resolve, 800));
    }

    const message = this.generateWelcomeMessage();
    await this.speak(message);
  }

  private generateWelcomeMessage(): string {
    const fullName = this.participantName.trim();
    const honorific = this.gender === 'Male' ? 'Mr.' : 'Ms.';
    const statusMsg = this.accessStatus === 'Accepted'
        ? 'Access granted. Welcome to our event.'
        : 'Access denied. Please contact the administration.';

    const accessMsg = this.getAccessTypeMessage();
    const qrMsg = this.accessStatus === 'Accepted'
        ? 'Your QR code has been successfully scanned.'
        : '';

    return `Dear ${honorific} ${fullName}, ${statusMsg} ${accessMsg} ${qrMsg}`.trim();
  }

  private getHonorific(): string {
    return this.gender === 'Male' ? 'Mr.' : 'Ms.';
  }

  private getAccessTypeMessage(): string {
    switch (this.accessType) {
      case 'fair':
        return 'You have access to the fair only.';
      case 'conference':
        return 'You have access to the conference only.';
      case 'fair + conference':
        return 'You have access to both the fair and the conference.';
      default:
        return '';
    }
  }

  private async speak(text: string) {
    if (!('speechSynthesis' in window)) return;

    if (this.synth.speaking) {
      this.synth.cancel();
      await new Promise(r => setTimeout(r, 150));
    }

    this.utterance = new SpeechSynthesisUtterance(text);
    this.utterance.lang = 'en-US';
    this.utterance.rate = 0.95;
    this.utterance.pitch = 1.05;
    this.utterance.volume = 1.0;
    if (this.selectedVoice) this.utterance.voice = this.selectedVoice;

    this.utterance.onstart = () => {
      this.ngZone.run(() => {
        this.isSpeaking = true;
        this.isPlaying = true;
        this.error = null;
        this.switchToSpeakingAvatar();
      });
    };

    this.utterance.onend = () => {
      this.ngZone.run(() => {
        this.isSpeaking = false;
        this.isPlaying = false;
        this.switchToSilentAvatar();
      });
    };

    this.utterance.onerror = (event) => {
      console.error('TTS error:', event);
      this.isSpeaking = false;
      this.isPlaying = false;
      if (event.error === 'interrupted') {
        this.retryTimeout = setTimeout(() => this.speak(text), 800);
      }
    };

    this.synth.speak(this.utterance);
  }

  private switchToSpeakingAvatar() {
    const silent = this.silentVideo?.nativeElement;
    const speaking = this.speakingVideo?.nativeElement;
    if (silent && !silent.paused) silent.pause();
    if (speaking) {
      speaking.currentTime = 0;
      speaking.play().catch(e => console.warn('Speaking video play failed:', e));
    }
  }

  private switchToSilentAvatar() {
    const silent = this.silentVideo?.nativeElement;
    const speaking = this.speakingVideo?.nativeElement;
    if (speaking && !speaking.paused) speaking.pause();
    if (silent) {
      silent.play().catch(e => console.warn('Silent video resume failed:', e));
    }
  }

  stopSpeaking() {
    if (this.synth.speaking) this.synth.cancel();
    this.isSpeaking = false;
    this.isPlaying = false;
  }

  replay() {
    this.playWelcomeMessage();
  }

  getAccessTypeDisplay(): string {
    switch (this.accessType) {
      case 'fair':
        return 'Fair only';
      case 'conference':
        return 'Conference only';
      case 'fair + conference':
        return 'Fair and Conference';
      default:
        return '';
    }
  }

  onVideoReady() {
    this.videoReady = true;
  }

  onVideoError() {
    this.error = 'Video error.';
  }
}