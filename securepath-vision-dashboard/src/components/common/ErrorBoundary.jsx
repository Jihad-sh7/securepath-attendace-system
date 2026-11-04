import React from "react";
import { AlertTriangle } from "lucide-react";

export default class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  render() {
    if (this.state.hasError) {
      return (
        <main className="error-boundary" role="alert">
          <AlertTriangle size={32} />
          <h1>Something went wrong</h1>
          <p>Refresh the app and try again. No biometric data was processed.</p>
        </main>
      );
    }

    return this.props.children;
  }
}
