window.POSVoiceChat = {
	pc: null,
	dc: null,
	audioEl: null,
	ms: null,
	isActive: false,
	messagesContainer: null,
	messageInput: null,
	startSessionButton: null,

	init() {
		document.addEventListener('DOMContentLoaded', this.onLoad.bind(this));
	},

	onLoad() {
		this.messagesContainer = document.getElementById('messages');
		this.messageInput = document.getElementById('message-input');
		this.startSessionButton = document.getElementById('start-session');
		document.getElementById('send-button').addEventListener('click', () => {
			this.addMessage(this.messageInput.value, 'user');
			this.messageInput.value = '';
		});
		this.startSessionButton.addEventListener('click', () => {
			this.realtimeChatInit(this.startSessionButton);
		});
	},

	markdownToHtml(markdown) {
		return markdown
			.replace(/\n/g, '<br>')
			.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
			.replace(/__(.*?)__/g, '<u>$1</u>')
			.replace(/\[(.*?)\]\((.*?)\)/g, '<a target="_blank" href="$2">$1</a>')
			.replace(/^\s*[-*]\s(.*)$/gm, '<li>$1</li>')
			.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
	},

	addMessage(text, sender) {
		text = this.markdownToHtml(text);
		const messageBubble = document.createElement('div');
		messageBubble.classList.add('message', sender);
		messageBubble.innerHTML = text;
		this.messagesContainer.appendChild(messageBubble);
		this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
	},

	async realtimeChatInit(button) {

		// If session is active, hang up
		if (this.isActive) {
			this.hangUpSession();
			button.textContent = "Start Session";
			button.classList.remove("session_active");
			return;
		}

		// Get ephemeral key from server
		const tokenResponse = await wp.apiFetch({
			path: '/pos/v1/openai/realtime/session',
			method: 'POST',
		});
		
		const EPHEMERAL_KEY = tokenResponse.client_secret.value;

		// Create a peer connection
		this.pc = new RTCPeerConnection();

		// Set up audio element
		this.audioEl = document.createElement("audio");
		this.audioEl.autoplay = true;
		document.body.appendChild(this.audioEl);
		this.pc.ontrack = e => this.audioEl.srcObject = e.streams[0];

		// Add local audio track
		this.ms = await navigator.mediaDevices.getUserMedia({
			audio: true
		});
		this.pc.addTrack(this.ms.getTracks()[0]);

		// Set up data channel
		this.dc = this.pc.createDataChannel("oai-events");
		this.dc.addEventListener('open', () => {
			button.textContent = "Hang Up";
			button.classList.add("session_active");

			this.dc.send(JSON.stringify({
				type: 'response.create',
				response: {
					"instructions": "Ask how can you",
				}
			}));
		});

		this.setupDataChannelListeners();

		// Start session
		const offer = await this.pc.createOffer();
		await this.pc.setLocalDescription(offer);

		const baseUrl = "https://api.openai.com/v1/realtime";
		const model = "gpt-4o-realtime-preview-2024-12-17";
		const sdpResponse = await fetch(`${baseUrl}?model=${model}`, {
			method: "POST",
			body: offer.sdp,
			headers: {
				Authorization: `Bearer ${EPHEMERAL_KEY}`,
				"Content-Type": "application/sdp"
			},
		});

		const answer = {
			type: "answer",
			sdp: await sdpResponse.text(),
		};
		await this.pc.setRemoteDescription(answer);
		this.isActive = true;
		button.textContent = "Connecting...";
	},

	setupDataChannelListeners() {
		this.dc.addEventListener("message", (e) => {
			const data = JSON.parse(e.data);
			if (data.type === 'response.function_call_arguments.done') {
				console.log('FUNCTION CALL ARGUMENTS DONE', data.name, data.arguments);
				this.addMessage('Calling function ' + data.name, 'bot');
				wp.apiFetch({
					path: '/pos/v1/openai/realtime/function_call',
					method: 'POST',
					data: {
						name: data.name,
						arguments: data.arguments
					}
				}).then(response => {
					console.log('FUNCTION CALL RESPONSE', response);
					this.dc.send(JSON.stringify({
						type: 'conversation.item.create',
						item: {
							type: 'function_call_output',
							call_id: data.call_id,
							output: response.result
						}
					}));
					this.dc.send(JSON.stringify({
						type: 'response.create'
					}));
				});
			} else if (data.type === 'response.audio_transcript.done') {
				console.log('MODEL', data.transcript);
				this.addMessage(data.transcript, 'bot');
			} else if (data.type === 'conversation.item.input_audio_transcription.completed') {
				console.log('USER', data.transcript);
				this.addMessage(data.transcript, 'user');
			}
		});
	},

	hangUpSession() {
		if (this.pc) {
			this.pc.close();
		}
		if (this.dc) {
			this.dc.close();
		}
		if (this.ms) {
			this.ms.getTracks().forEach(track => track.stop());
		}
		if (this.audioEl) {
			this.audioEl.remove();
		}

		// Reset all variables
		this.pc = null;
		this.dc = null;
		this.audioEl = null;
		this.ms = null;
		this.isActive = false;
	}
};

// Initialize the chat
POSVoiceChat.init();
