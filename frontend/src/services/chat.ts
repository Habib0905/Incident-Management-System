import api from '@/lib/api';
import { ChatMessage } from '@/types';

export const chatService = {
  async sendMessage(message: string, history: ChatMessage[]): Promise<string> {
    const response = await api.post<{ response: string }>('/chat', {
      message,
      history,
    });
    return response.data.response;
  },
};
