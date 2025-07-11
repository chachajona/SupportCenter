import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import axios from '@/lib/axios';
import { PageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import { Bot, Clock, MessageCircle, Phone, Search, Send, Star, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ChatMessage {
    id: number;
    content: string;
    sender_type: 'user' | 'bot' | 'system';
    sender_name: string;
    timestamp: string;
    confidence?: number;
    intent?: any;
    knowledge_articles?: any[];
    suggested_actions?: string[];
}

interface ChatbotResponse {
    message: string;
    intent: any;
    knowledge_articles: any[];
    confidence: number;
    should_escalate: boolean;
    suggested_actions: string[];
    timestamp: string;
}

interface Conversation {
    id: number;
    session_id: string;
    status: string;
    started_at: string;
    total_messages: number;
    average_confidence: number;
}

export default function CustomerPortal({ auth }: PageProps) {
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [inputMessage, setInputMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [chatStarted, setChatStarted] = useState(false);
    const [showFeedback, setShowFeedback] = useState(false);
    const [rating, setRating] = useState(0);
    const [feedback, setFeedback] = useState('');

    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const startChat = async () => {
        setIsLoading(true);
        try {
            const response = await axios.post('/api/portal/chat/start', {
                channel: 'web_chat',
                user_id: auth?.user?.id,
            });

            if (response.data.success) {
                setConversation(response.data.conversation);
                setChatStarted(true);

                // Add initial bot message
                setMessages([
                    {
                        id: 1,
                        content: "Hello! I'm your AI support assistant. How can I help you today?",
                        sender_type: 'bot',
                        sender_name: 'Support Bot',
                        timestamp: new Date().toISOString(),
                    },
                ]);
            }
        } catch (error) {
            console.error('Failed to start chat:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const sendMessage = async () => {
        if (!inputMessage.trim() || !conversation) return;

        const userMessage: ChatMessage = {
            id: Date.now(),
            content: inputMessage,
            sender_type: 'user',
            sender_name: auth?.user?.name || 'You',
            timestamp: new Date().toISOString(),
        };

        setMessages((prev) => [...prev, userMessage]);
        setInputMessage('');
        setIsLoading(true);

        try {
            const response = await axios.post('/api/portal/chat/message', {
                session_id: conversation.session_id,
                message: inputMessage,
                user_id: auth?.user?.id,
            });

            if (response.data.success) {
                const botResponse: ChatbotResponse = response.data.response;

                const botMessage: ChatMessage = {
                    id: Date.now() + 1,
                    content: botResponse.message,
                    sender_type: 'bot',
                    sender_name: 'Support Bot',
                    timestamp: botResponse.timestamp,
                    confidence: botResponse.confidence,
                    intent: botResponse.intent,
                    knowledge_articles: botResponse.knowledge_articles,
                    suggested_actions: botResponse.suggested_actions,
                };

                setMessages((prev) => [...prev, botMessage]);
                setConversation(response.data.conversation);
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            const errorMessage: ChatMessage = {
                id: Date.now() + 1,
                content: "I'm sorry, I'm having trouble processing your request. Please try again.",
                sender_type: 'bot',
                sender_name: 'Support Bot',
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const escalateToHuman = async () => {
        if (!conversation) return;

        setIsLoading(true);
        try {
            const response = await axios.post('/api/portal/chat/escalate', {
                session_id: conversation.session_id,
                reason: 'User requested human assistance',
                user_id: auth?.user?.id,
            });

            if (response.data.success) {
                const systemMessage: ChatMessage = {
                    id: Date.now(),
                    content: `I've connected you with a human agent. A support ticket has been created (#${response.data.ticket.id}) and you'll receive assistance shortly.`,
                    sender_type: 'system',
                    sender_name: 'System',
                    timestamp: new Date().toISOString(),
                };
                setMessages((prev) => [...prev, systemMessage]);
                setConversation(response.data.conversation);
            }
        } catch (error) {
            console.error('Failed to escalate:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const endChat = async () => {
        if (!conversation) return;

        setIsLoading(true);
        try {
            const response = await axios.post('/api/portal/chat/end', {
                session_id: conversation.session_id,
                reason: 'user_ended',
                rating: rating > 0 ? rating : undefined,
                feedback: feedback || undefined,
            });

            if (response.data.success) {
                setShowFeedback(false);
                setChatStarted(false);
                setMessages([]);
                setConversation(null);
            }
        } catch (error) {
            console.error('Failed to end chat:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    const handleActionClick = (action: string) => {
        switch (action) {
            case 'escalate_to_human':
                escalateToHuman();
                break;
            case 'create_ticket':
                // Navigate to ticket creation
                window.open('/tickets/create', '_blank');
                break;
            case 'search_knowledge':
                // Navigate to knowledge base
                window.open('/knowledge', '_blank');
                break;
            default:
                console.log('Action clicked:', action);
        }
    };

    const MessageComponent = ({ message }: { message: ChatMessage }) => (
        <div className={`flex ${message.sender_type === 'user' ? 'justify-end' : 'justify-start'} mb-4`}>
            <div className={`flex max-w-[80%] ${message.sender_type === 'user' ? 'flex-row-reverse' : 'flex-row'}`}>
                <div
                    className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ${
                        message.sender_type === 'user' ? 'ml-2 bg-blue-500' : 'mr-2 bg-gray-500'
                    }`}
                >
                    {message.sender_type === 'user' ? <User className="h-4 w-4 text-white" /> : <Bot className="h-4 w-4 text-white" />}
                </div>

                <div
                    className={`rounded-lg p-3 ${
                        message.sender_type === 'user'
                            ? 'bg-blue-500 text-white'
                            : message.sender_type === 'system'
                              ? 'bg-yellow-100 text-yellow-800'
                              : 'bg-gray-100 text-gray-800'
                    }`}
                >
                    <p className="text-sm">{message.content}</p>

                    {message.confidence && <div className="mt-2 text-xs opacity-75">Confidence: {Math.round(message.confidence * 100)}%</div>}

                    {message.knowledge_articles && message.knowledge_articles.length > 0 && (
                        <div className="mt-2 space-y-1">
                            <p className="text-xs font-semibold">Related Articles:</p>
                            {message.knowledge_articles.map((article, index) => (
                                <a
                                    key={index}
                                    href={article.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block text-xs underline hover:no-underline"
                                >
                                    {article.title}
                                </a>
                            ))}
                        </div>
                    )}

                    {message.suggested_actions && message.suggested_actions.length > 0 && (
                        <div className="mt-2 space-y-1">
                            {message.suggested_actions.map((action, index) => (
                                <Button key={index} variant="outline" size="sm" onClick={() => handleActionClick(action)} className="mr-2 mb-1">
                                    {action.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                                </Button>
                            ))}
                        </div>
                    )}

                    <div className="mt-1 text-xs opacity-50">{new Date(message.timestamp).toLocaleTimeString()}</div>
                </div>
            </div>
        </div>
    );

    return (
        <AppLayout>
            <Head title="Customer Portal - Support Center" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-foreground text-3xl font-bold">🎧 Customer Portal</h1>
                    <p className="text-muted-foreground mt-2">Get instant help with our AI-powered support assistant</p>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Chat Interface */}
                    <div className="lg:col-span-2">
                        <Card className="flex h-[600px] flex-col">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageCircle className="h-5 w-5" />
                                    AI Support Chat
                                    {conversation && (
                                        <Badge variant="outline" className="ml-auto">
                                            {conversation.status}
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription>Chat with our AI assistant for instant support</CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-1 flex-col">
                                {!chatStarted ? (
                                    <div className="flex flex-1 items-center justify-center">
                                        <div className="text-center">
                                            <Bot className="mx-auto mb-4 h-16 w-16 text-gray-400" />
                                            <h3 className="mb-2 text-lg font-semibold">Start a conversation</h3>
                                            <p className="text-muted-foreground mb-4">Get instant help from our AI support assistant</p>
                                            <Button onClick={startChat} disabled={isLoading}>
                                                {isLoading ? 'Starting...' : 'Start Chat'}
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        {/* Messages */}
                                        <div className="mb-4 flex-1 space-y-4 overflow-y-auto">
                                            {messages.map((message) => (
                                                <MessageComponent key={message.id} message={message} />
                                            ))}
                                            <div ref={messagesEndRef} />
                                        </div>

                                        {/* Input */}
                                        <div className="border-t pt-4">
                                            <div className="flex gap-2">
                                                <Textarea
                                                    value={inputMessage}
                                                    onChange={(e) => setInputMessage(e.target.value)}
                                                    onKeyPress={handleKeyPress}
                                                    placeholder="Type your message..."
                                                    className="flex-1 resize-none"
                                                    rows={2}
                                                    disabled={isLoading || conversation?.status === 'escalated'}
                                                />
                                                <div className="flex flex-col gap-2">
                                                    <Button
                                                        onClick={sendMessage}
                                                        disabled={!inputMessage.trim() || isLoading || conversation?.status === 'escalated'}
                                                        size="sm"
                                                    >
                                                        <Send className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        onClick={escalateToHuman}
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={isLoading || conversation?.status === 'escalated'}
                                                    >
                                                        <Phone className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="mt-2 flex items-center justify-between">
                                                <p className="text-muted-foreground text-xs">Press Enter to send, Shift+Enter for new line</p>
                                                <div className="flex gap-2">
                                                    <Button onClick={() => setShowFeedback(true)} variant="ghost" size="sm" disabled={!conversation}>
                                                        Rate Chat
                                                    </Button>
                                                    <Button onClick={endChat} variant="ghost" size="sm" disabled={!conversation}>
                                                        End Chat
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Search className="h-5 w-5" />
                                    Quick Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button variant="outline" className="w-full justify-start" onClick={() => window.open('/knowledge', '_blank')}>
                                    Search Knowledge Base
                                </Button>
                                <Button variant="outline" className="w-full justify-start" onClick={() => window.open('/tickets/create', '_blank')}>
                                    Create Support Ticket
                                </Button>
                                <Button variant="outline" className="w-full justify-start" onClick={() => window.open('/tickets', '_blank')}>
                                    View My Tickets
                                </Button>
                            </CardContent>
                        </Card>

                        {conversation && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="h-5 w-5" />
                                        Chat Stats
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground text-sm">Messages:</span>
                                        <span className="text-sm font-medium">{conversation.total_messages}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground text-sm">Confidence:</span>
                                        <span className="text-sm font-medium">{Math.round(conversation.average_confidence * 100)}%</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground text-sm">Status:</span>
                                        <Badge variant="outline">{conversation.status}</Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Feedback Modal */}
                {showFeedback && (
                    <div className="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black">
                        <Card className="w-full max-w-md">
                            <CardHeader>
                                <CardTitle>Rate Your Experience</CardTitle>
                                <CardDescription>How was your chat experience?</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-center space-x-2">
                                    {[1, 2, 3, 4, 5].map((star) => (
                                        <button
                                            key={star}
                                            onClick={() => setRating(star)}
                                            className={`text-2xl ${star <= rating ? 'text-yellow-400' : 'text-gray-300'}`}
                                        >
                                            <Star className="h-6 w-6" fill={star <= rating ? 'currentColor' : 'none'} />
                                        </button>
                                    ))}
                                </div>
                                <Textarea
                                    value={feedback}
                                    onChange={(e) => setFeedback(e.target.value)}
                                    placeholder="Additional feedback (optional)"
                                    rows={3}
                                />
                                <div className="flex gap-2">
                                    <Button onClick={() => setShowFeedback(false)} variant="outline" className="flex-1">
                                        Cancel
                                    </Button>
                                    <Button onClick={endChat} className="flex-1">
                                        Submit & End Chat
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
