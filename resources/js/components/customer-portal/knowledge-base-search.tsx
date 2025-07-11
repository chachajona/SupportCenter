import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import axios from '@/lib/axios';
import { debounce } from 'lodash';
import { AlertCircle, BookOpen, Clock, Eye, Lightbulb, MessageCircle, Search, Star, ThumbsUp, TrendingUp } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface KnowledgeArticle {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    category: string;
    helpful_count: number;
    view_count: number;
    url: string;
    relevance_score: number;
}

interface SearchResult {
    articles: KnowledgeArticle[];
    total_results: number;
    search_suggestions: string[];
}

interface TroubleshootingStep {
    step: number;
    title: string;
    description: string;
    estimated_time: string;
}

interface PredictiveSuggestion {
    based_on_history?: string[];
    trending_issues: string[];
    seasonal: string[];
}

interface KnowledgeBaseSearchProps {
    onArticleSelect?: (article: KnowledgeArticle) => void;
    onStartChat?: (initialMessage: string) => void;
    className?: string;
}

export default function KnowledgeBaseSearch({ onArticleSelect, onStartChat, className = '' }: KnowledgeBaseSearchProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResult | null>(null);
    const [popularArticles, setPopularArticles] = useState<KnowledgeArticle[]>([]);
    const [recentArticles, setRecentArticles] = useState<KnowledgeArticle[]>([]);
    const [troubleshootingSteps, setTroubleshootingSteps] = useState<TroubleshootingStep[]>([]);
    const [predictiveSuggestions, setPredictiveSuggestions] = useState<PredictiveSuggestion | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedTab, setSelectedTab] = useState<'search' | 'popular' | 'recent' | 'troubleshooting'>('search');

    // Debounced search function
    const debouncedSearch = useCallback(
        debounce(async (query: string) => {
            if (!query.trim()) {
                setSearchResults(null);
                return;
            }

            setIsLoading(true);
            try {
                const response = await axios.get('/api/knowledge/search', {
                    params: { query, limit: 10 },
                });
                setSearchResults(response.data);
            } catch (error) {
                console.error('Search failed:', error);
            } finally {
                setIsLoading(false);
            }
        }, 300),
        [],
    );

    // Load initial data
    useEffect(() => {
        loadInitialData();
    }, []);

    // Search when query changes
    useEffect(() => {
        debouncedSearch(searchQuery);
    }, [searchQuery, debouncedSearch]);

    const loadInitialData = async () => {
        try {
            const [popularResponse, recentResponse, suggestionsResponse] = await Promise.all([
                axios.get('/api/knowledge/popular'),
                axios.get('/api/knowledge/recent'),
                axios.get('/api/portal/suggestions'),
            ]);

            setPopularArticles(popularResponse.data.articles || []);
            setRecentArticles(recentResponse.data.articles || []);
            setPredictiveSuggestions(suggestionsResponse.data);
        } catch (error) {
            console.error('Failed to load initial data:', error);
        }
    };

    const handleSearch = (query: string) => {
        setSearchQuery(query);
        setSelectedTab('search');
    };

    const getTroubleshooting = async (issue: string) => {
        setIsLoading(true);
        try {
            const response = await axios.get('/api/portal/troubleshooting', {
                params: { issue },
            });
            setTroubleshootingSteps(response.data.steps || []);
            setSelectedTab('troubleshooting');
        } catch (error) {
            console.error('Failed to get troubleshooting steps:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const ArticleCard = ({ article }: { article: KnowledgeArticle }) => (
        <Card className="cursor-pointer transition-shadow hover:shadow-md" onClick={() => onArticleSelect?.(article)}>
            <CardContent className="p-4">
                <div className="mb-2 flex items-start justify-between">
                    <h4 className="line-clamp-2 text-sm font-semibold">{article.title}</h4>
                    <Badge variant="secondary" className="ml-2 text-xs">
                        {article.category}
                    </Badge>
                </div>
                <p className="text-muted-foreground mb-3 line-clamp-2 text-xs">{article.excerpt}</p>
                <div className="text-muted-foreground flex items-center justify-between text-xs">
                    <div className="flex items-center space-x-3">
                        <span className="flex items-center">
                            <Eye className="mr-1 h-3 w-3" />
                            {article.view_count}
                        </span>
                        <span className="flex items-center">
                            <ThumbsUp className="mr-1 h-3 w-3" />
                            {article.helpful_count}
                        </span>
                    </div>
                    {article.relevance_score && (
                        <Badge variant="outline" className="text-xs">
                            {Math.round(article.relevance_score * 100)}% match
                        </Badge>
                    )}
                </div>
            </CardContent>
        </Card>
    );

    const SuggestionChip = ({ text, onClick }: { text: string; onClick: () => void }) => (
        <Button variant="outline" size="sm" onClick={onClick} className="mr-2 mb-2 h-7 text-xs">
            {text}
        </Button>
    );

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Search Header */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Search className="h-5 w-5" />
                        Knowledge Base Search
                    </CardTitle>
                    <CardDescription>Find answers, troubleshooting guides, and helpful resources</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="relative">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                        <Input
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search for help, guides, or common issues..."
                            className="pl-10"
                        />
                    </div>

                    {/* Search suggestions */}
                    {searchResults?.search_suggestions && searchResults.search_suggestions.length > 0 && (
                        <div className="mt-3">
                            <p className="mb-2 text-sm font-medium">Suggestions:</p>
                            <div className="flex flex-wrap">
                                {searchResults.search_suggestions.map((suggestion, index) => (
                                    <SuggestionChip key={index} text={suggestion} onClick={() => handleSearch(suggestion)} />
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Tab Navigation */}
            <div className="flex space-x-2 border-b">
                {[
                    { key: 'search', label: 'Search Results', icon: Search },
                    { key: 'popular', label: 'Popular', icon: TrendingUp },
                    { key: 'recent', label: 'Recent', icon: Clock },
                    { key: 'troubleshooting', label: 'Troubleshooting', icon: Lightbulb },
                ].map(({ key, label, icon: Icon }) => (
                    <Button
                        key={key}
                        variant={selectedTab === key ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setSelectedTab(key as any)}
                        className="flex items-center gap-2"
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </Button>
                ))}
            </div>

            {/* Content Area */}
            <div className="min-h-[400px]">
                {isLoading && (
                    <div className="flex h-40 items-center justify-center">
                        <div className="text-center">
                            <div className="border-primary mx-auto mb-2 h-8 w-8 animate-spin rounded-full border-b-2"></div>
                            <p className="text-muted-foreground text-sm">Searching...</p>
                        </div>
                    </div>
                )}

                {/* Search Results */}
                {selectedTab === 'search' && !isLoading && (
                    <div>
                        {searchResults ? (
                            <>
                                {searchResults.articles.length > 0 ? (
                                    <>
                                        <div className="mb-4">
                                            <p className="text-muted-foreground text-sm">
                                                Found {searchResults.total_results} results for "{searchQuery}"
                                            </p>
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            {searchResults.articles.map((article) => (
                                                <ArticleCard key={article.id} article={article} />
                                            ))}
                                        </div>
                                    </>
                                ) : (
                                    <Card>
                                        <CardContent className="p-8 text-center">
                                            <AlertCircle className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                            <h3 className="mb-2 font-semibold">No results found</h3>
                                            <p className="text-muted-foreground mb-4">
                                                Try searching with different keywords or browse our popular articles.
                                            </p>
                                            {onStartChat && (
                                                <Button onClick={() => onStartChat(searchQuery)}>
                                                    <MessageCircle className="mr-2 h-4 w-4" />
                                                    Ask our AI assistant
                                                </Button>
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        ) : (
                            <Card>
                                <CardContent className="p-8 text-center">
                                    <BookOpen className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                    <h3 className="mb-2 font-semibold">Start your search</h3>
                                    <p className="text-muted-foreground">Enter keywords above to find helpful articles and guides.</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Popular Articles */}
                {selectedTab === 'popular' && !isLoading && (
                    <div>
                        <div className="mb-4">
                            <h3 className="mb-2 font-semibold">Most Popular Articles</h3>
                            <p className="text-muted-foreground text-sm">Articles that have helped the most users</p>
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {popularArticles.map((article) => (
                                <ArticleCard key={article.id} article={article} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Recent Articles */}
                {selectedTab === 'recent' && !isLoading && (
                    <div>
                        <div className="mb-4">
                            <h3 className="mb-2 font-semibold">Recently Added</h3>
                            <p className="text-muted-foreground text-sm">Latest articles and updates</p>
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {recentArticles.map((article) => (
                                <ArticleCard key={article.id} article={article} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Troubleshooting */}
                {selectedTab === 'troubleshooting' && !isLoading && (
                    <div>
                        <div className="mb-4">
                            <h3 className="mb-2 font-semibold">Guided Troubleshooting</h3>
                            <p className="text-muted-foreground text-sm">Step-by-step guides to resolve common issues</p>
                        </div>

                        {troubleshootingSteps.length > 0 ? (
                            <div className="space-y-4">
                                {troubleshootingSteps.map((step) => (
                                    <Card key={step.step}>
                                        <CardContent className="p-4">
                                            <div className="flex items-start space-x-3">
                                                <div className="bg-primary text-primary-foreground flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                                                    {step.step}
                                                </div>
                                                <div className="flex-1">
                                                    <h4 className="mb-1 font-semibold">{step.title}</h4>
                                                    <p className="text-muted-foreground mb-2 text-sm">{step.description}</p>
                                                    <Badge variant="outline" className="text-xs">
                                                        <Clock className="mr-1 h-3 w-3" />
                                                        {step.estimated_time}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="p-8 text-center">
                                    <Lightbulb className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                    <h3 className="mb-2 font-semibold">Get Troubleshooting Help</h3>
                                    <p className="text-muted-foreground mb-4">Describe your issue to get step-by-step troubleshooting guidance.</p>
                                    <div className="mx-auto max-w-md">
                                        <Input
                                            placeholder="Describe your issue..."
                                            onKeyPress={(e) => {
                                                if (e.key === 'Enter') {
                                                    getTroubleshooting(e.currentTarget.value);
                                                }
                                            }}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>

            {/* Predictive Suggestions */}
            {predictiveSuggestions && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Star className="h-5 w-5" />
                            Suggested Topics
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {predictiveSuggestions.trending_issues.length > 0 && (
                                <div>
                                    <h4 className="mb-2 flex items-center gap-2 font-medium">
                                        <TrendingUp className="h-4 w-4" />
                                        Trending Issues
                                    </h4>
                                    <div className="flex flex-wrap">
                                        {predictiveSuggestions.trending_issues.slice(0, 5).map((issue, index) => (
                                            <SuggestionChip key={index} text={issue} onClick={() => handleSearch(issue)} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {predictiveSuggestions.seasonal.length > 0 && (
                                <div>
                                    <h4 className="mb-2 flex items-center gap-2 font-medium">
                                        <Clock className="h-4 w-4" />
                                        Seasonal Help
                                    </h4>
                                    <div className="flex flex-wrap">
                                        {predictiveSuggestions.seasonal.map((topic, index) => (
                                            <SuggestionChip key={index} text={topic} onClick={() => handleSearch(topic)} />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
