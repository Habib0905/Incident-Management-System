'use client';

import { useState, useRef, useEffect } from 'react';
import { chatService } from '@/services/chat';
import { ChatMessage } from '@/types';
import { MessageSquare, X, Send, Loader2 } from 'lucide-react';

const MAX_HISTORY = 4;

function ChatMessageContent({ content, isUser }: { content: string; isUser: boolean }) {
  const parts: React.ReactNode[] = [];
  const lines = content.split('\n');

  lines.forEach((line, lineIdx) => {
    if (lineIdx > 0) {
      parts.push(<br key={`br-${lineIdx}`} />);
    }

    const segments: React.ReactNode[] = [];
    const regex = /\*\*(.+?)\*\*/g;
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(line)) !== null) {
      if (match.index > lastIndex) {
        segments.push(line.slice(lastIndex, match.index));
      }
      segments.push(
        <strong key={`${lineIdx}-${match.index}`} className={isUser ? 'font-semibold' : 'font-semibold'}>
          {match[1]}
        </strong>
      );
      lastIndex = regex.lastIndex;
    }

    if (lastIndex < line.length) {
      segments.push(line.slice(lastIndex));
    }

    if (segments.length === 0) {
      segments.push('\u00A0');
    }

    parts.push(<span key={`line-${lineIdx}`}>{segments}</span>);
  });

  return <>{parts}</>;
}

export function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  useEffect(() => {
    if (isOpen) {
      inputRef.current?.focus();
    }
  }, [isOpen]);

  const handleSend = async () => {
    if (!input.trim() || isLoading) return;

    const userMessage: ChatMessage = { role: 'user', content: input.trim() };
    const updatedMessages = [...messages, userMessage];

    setMessages(updatedMessages);
    setInput('');
    setIsLoading(true);

    try {
      const history = updatedMessages.slice(-MAX_HISTORY);
      const response = await chatService.sendMessage(userMessage.content, history);
      setMessages([...updatedMessages, { role: 'assistant', content: response }]);
    } catch {
      setMessages([...updatedMessages, { role: 'assistant', content: 'Sorry, I encountered an error. Please try again.' }]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleClose = () => {
    setIsOpen(false);
  };

  if (!isOpen) {
    return (
      <button
        onClick={() => setIsOpen(true)}
        className="fixed bottom-6 right-6 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-colors z-50"
        title="Open Incident Assistant"
      >
        <MessageSquare className="h-6 w-6" />
      </button>
    );
  }

  return (
    <div className="fixed bottom-6 right-6 w-96 max-h-[500px] bg-white rounded-xl shadow-2xl border border-gray-200 flex flex-col z-50">
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-xl">
        <div>
          <h3 className="font-semibold text-gray-900 text-sm">Incident Assistant</h3>
          <span className="text-xs text-gray-500">Read-only</span>
        </div>
        <button
          onClick={handleClose}
          className="p-1.5 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-200 transition-colors"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-3 min-h-[300px] max-h-[380px]">
        {messages.length === 0 && (
          <div className="text-center text-gray-400 text-sm py-8">
            <MessageSquare className="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p>Ask me about incidents, logs, or servers</p>
            <p className="text-xs mt-1">Type &quot;help&quot; to see what I can do</p>
          </div>
        )}

        {messages.map((msg, i) => (
          <div
            key={i}
            className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
          >
            <div
              className={`max-w-[85%] px-3 py-2 rounded-lg text-sm whitespace-pre-wrap ${
                msg.role === 'user'
                  ? 'bg-blue-600 text-white rounded-br-none'
                  : 'bg-gray-100 text-gray-900 rounded-bl-none'
              }`}
            >
              <ChatMessageContent content={msg.content} isUser={msg.role === 'user'} />
            </div>
          </div>
        ))}

        {isLoading && (
          <div className="flex justify-start">
            <div className="bg-gray-100 px-3 py-2 rounded-lg rounded-bl-none">
              <Loader2 className="h-4 w-4 animate-spin text-gray-500" />
            </div>
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      <div className="px-3 py-3 border-t border-gray-200 rounded-b-xl">
        <div className="flex items-center gap-2">
          <input
            ref={inputRef}
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Type a message..."
            className="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
            disabled={isLoading}
          />
          <button
            onClick={handleSend}
            disabled={!input.trim() || isLoading}
            className="p-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <Send className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
