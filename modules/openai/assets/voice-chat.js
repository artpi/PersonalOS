window.pos_voice_chat = {
	pc: null,
	dc: null,
	audioEl: null,
	ms: null,
	isActive: false
};

function markdownToHtml(markdown) {
	return markdown
		.replace(/\n/g, '<br>')
		.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
		.replace(/__(.*?)__/g, '<u>$1</u>')
		.replace(/\[(.*?)\]\((.*?)\)/g, '<a target="_blank" href="$2">$1</a>')
		.replace(/^\s*[-*]\s(.*)$/gm, '<li>$1</li>')
		.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
}


async function realtimeChatInit(clickEvent) {
	const messagesContainer = document.getElementById('messages');
	const messageInput = document.getElementById('message-input');
	const sendButton = document.getElementById('send-button');

	function addMessage(text, sender) {
		text = markdownToHtml(text);
		const messageBubble = document.createElement('div');
		messageBubble.classList.add('message', sender);
		messageBubble.innerHTML = text;
		messagesContainer.appendChild(messageBubble);
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}

	const button = clickEvent.target;

	// If session is active, hang up
	if (window.pos_voice_chat.isActive) {
		hangUpSession();
		button.textContent = "Start Session";
		button.classList.remove("session_active");
		return;
	}

	// Get ephemeral key from server
	const tokenResponse = await wp.apiFetch({
		path: '/pos/v1/openai/realtime/session',
		method: 'POST',
	});
	
	const data = tokenResponse;
	const EPHEMERAL_KEY = data.client_secret.value;

	// Create a peer connection
	window.pos_voice_chat.pc = new RTCPeerConnection();
	const pc = window.pos_voice_chat.pc;

	// Set up audio element
	window.pos_voice_chat.audioEl = document.createElement("audio");
	window.pos_voice_chat.audioEl.autoplay = true;
	document.body.appendChild(window.pos_voice_chat.audioEl);
	pc.ontrack = e => window.pos_voice_chat.audioEl.srcObject = e.streams[0];

	// Add local audio track
	window.pos_voice_chat.ms = await navigator.mediaDevices.getUserMedia({
		audio: true
	});
	pc.addTrack(window.pos_voice_chat.ms.getTracks()[0]);

	// Set up data channel
	window.pos_voice_chat.dc = pc.createDataChannel("oai-events");
	window.pos_voice_chat.dc.addEventListener('open', () => {

		// Update UI to show active session
		button.textContent = "Hang Up";
		button.classList.add("session_active");

		window.pos_voice_chat.dc.send(JSON.stringify({
			type: 'response.create',
			response: {
				"instructions": "Ask how can you",
			}
		}));
	});
	window.pos_voice_chat.dc.addEventListener("message", (e) => {
		const data = JSON.parse(e.data);
		if ( data.type === 'response.function_call_arguments.done' ) {
			console.log('FUNCTION CALL ARGUMENTS DONE', data.name, data.arguments);
			addMessage('Calling function ' + data.name, 'bot');
			wp.apiFetch({
				path: '/pos/v1/openai/realtime/function_call',
				method: 'POST',
				data: {
					name: data.name,
					arguments: data.arguments
				}
			}).then(response => {
				console.log('FUNCTION CALL RESPONSE', response);
				window.pos_voice_chat.dc.send(JSON.stringify({
					type: 'conversation.item.create',
					item: {
						type: 'function_call_output',
						call_id: data.call_id,
						output: response.result
					}
				}));
				window.pos_voice_chat.dc.send(JSON.stringify({
					type: 'response.create'
				}));

			});

		} else if (data.type === 'response.audio_transcript.done') {
			console.log('MODEL', data.transcript);
			addMessage(data.transcript, 'bot');
		} else if (data.type === 'conversation.item.input_audio_transcription.completed') {
			console.log('USER', data.transcript);
			addMessage(data.transcript, 'user');
		}
	});

	// Start session
	const offer = await pc.createOffer();
	await pc.setLocalDescription(offer);

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
	await pc.setRemoteDescription(answer);
	window.pos_voice_chat.isActive = true;
	button.textContent = "Connecting...";
}

function hangUpSession() {
	if (window.pos_voice_chat.pc) {
		window.pos_voice_chat.pc.close();
	}
	if (window.pos_voice_chat.dc) {
		window.pos_voice_chat.dc.close();
	}
	if (window.pos_voice_chat.ms) {
		window.pos_voice_chat.ms.getTracks().forEach(track => track.stop());
	}
	if (window.pos_voice_chat.audioEl) {
		window.pos_voice_chat.audioEl.remove();
	}

	// Reset all variables
	window.pos_voice_chat = {
		pc: null,
		dc: null,
		audioEl: null, 
		ms: null,
		isActive: false
	};
}
