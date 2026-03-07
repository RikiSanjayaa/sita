import React, { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Uncaught error:', error, errorInfo);
  }

  public render() {
    if (this.state.hasError) {
      return (
        this.props.fallback || (
          <div className="flex min-h-[400px] w-full flex-col items-center justify-center rounded-lg border border-red-200 bg-red-50 p-6 text-center dark:border-red-900/50 dark:bg-red-900/20">
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50">
              <span className="text-xl font-bold text-red-600 dark:text-red-400">!</span>
            </div>
            <h2 className="mb-2 text-lg font-semibold text-red-800 dark:text-red-300">
              Something went wrong
            </h2>
            <p className="mb-4 max-w-md text-sm text-red-600 dark:text-red-400">
              The calendar component encountered an error and could not be displayed.
            </p>
            {this.state.error && (
              <pre className="max-w-full overflow-auto rounded bg-red-100 p-2 text-left text-xs text-red-800 dark:bg-red-900/50 dark:text-red-400">
                {this.state.error.message}
              </pre>
            )}
            <button
              onClick={() => this.setState({ hasError: false, error: null })}
              className="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
              Try again
            </button>
          </div>
        )
      );
    }

    return this.props.children;
  }
}
