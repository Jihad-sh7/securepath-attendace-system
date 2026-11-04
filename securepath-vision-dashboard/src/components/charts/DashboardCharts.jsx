import {
  Area,
  AreaChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis
} from "recharts";
import ChartCard from "./ChartCard.jsx";

const chartColors = ["#4f46e5", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6"];

export function AttendanceTrendChart({ data }) {
  return (
    <ChartCard title="7-Day Attendance Trend" className="wide">
      <ResponsiveContainer width="100%" height={280}>
        <AreaChart data={data}>
          <defs>
            <linearGradient id="presentFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.32} />
              <stop offset="95%" stopColor="#4f46e5" stopOpacity={0.04} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
          <XAxis dataKey="day" tickLine={false} axisLine={false} />
          <YAxis tickLine={false} axisLine={false} width={42} />
          <Tooltip />
          <Legend />
          <Area type="monotone" dataKey="present" stroke="#4f46e5" fill="url(#presentFill)" strokeWidth={3} />
          <Line type="monotone" dataKey="late" stroke="#f59e0b" strokeWidth={2} dot={false} />
        </AreaChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

export function DepartmentBars({ data }) {
  return (
    <ChartCard title="By Department">
      <div className="progress-list">
        {data.map((item, index) => (
          <div key={item.id || item.name}>
            <div>
              <span>{item.name}</span>
              <strong>{item.attendanceRate}%</strong>
            </div>
            <span className="progress-track">
              <i style={{ width: `${item.attendanceRate}%`, background: chartColors[index % chartColors.length] }} />
            </span>
          </div>
        ))}
      </div>
    </ChartCard>
  );
}

export function StatusBreakdownChart({ data }) {
  return (
    <ChartCard title="Attendance Status Breakdown">
      <ResponsiveContainer width="100%" height={240}>
        <PieChart>
          <Pie data={data} dataKey="value" nameKey="name" innerRadius={54} outerRadius={82} paddingAngle={4}>
            {data.map((entry, index) => (
              <Cell key={entry.name} fill={chartColors[index % chartColors.length]} />
            ))}
          </Pie>
          <Tooltip />
          <Legend />
        </PieChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

export function AccuracyChart({ data }) {
  return (
    <ChartCard title="Recognition Accuracy">
      <ResponsiveContainer width="100%" height={250}>
        <LineChart data={data}>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
          <XAxis dataKey="day" tickLine={false} axisLine={false} />
          <YAxis domain={[85, 100]} tickLine={false} axisLine={false} />
          <Tooltip />
          <Line type="monotone" dataKey="confidence" stroke="#8b5cf6" strokeWidth={3} />
        </LineChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}
