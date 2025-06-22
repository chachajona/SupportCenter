import { AlertCircle, CheckCircle, Clock, TicketIcon } from 'lucide-react';
import { TicketStatsCard } from './ticket-stats-card';

// Mock data for ticket statistics
const ticketStats = {
    total: {
        count: 1247,
        trend: { value: 12, isPositive: true },
    },
    open: {
        count: 324,
        trend: { value: 8, isPositive: false },
    },
    pending: {
        count: 156,
        trend: { value: 15, isPositive: false },
    },
    solved: {
        count: 767,
        trend: { value: 23, isPositive: true },
    },
};

export function TicketVolumeOverview() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <TicketStatsCard
                title="Total Tickets"
                count={ticketStats.total.count}
                icon={TicketIcon}
                trend={ticketStats.total.trend}
                color="blue"
                className="col-span-1"
            />

            <TicketStatsCard
                title="Open Tickets"
                count={ticketStats.open.count}
                icon={AlertCircle}
                trend={ticketStats.open.trend}
                color="orange"
                className="col-span-1"
            />

            <TicketStatsCard
                title="Pending Tickets"
                count={ticketStats.pending.count}
                icon={Clock}
                trend={ticketStats.pending.trend}
                color="purple"
                className="col-span-1"
            />

            <TicketStatsCard
                title="Solved Tickets"
                count={ticketStats.solved.count}
                icon={CheckCircle}
                trend={ticketStats.solved.trend}
                color="green"
                className="col-span-1"
            />
        </div>
    );
}
