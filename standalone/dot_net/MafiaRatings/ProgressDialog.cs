using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.Threading;

namespace MafiaRatings
{
    public partial class ProgressDialog : Form
    {
        private const int DELAY = 1000;
        private const double OPACITY_INCREMENT = 0.3;
        private const double OPACITY_DECREMENT = 0.3;
        private const double MAX_OPACITY = 1.0;
        private const int MIN_TIME_ON_SCREEN = 500;

        private enum State
        {
            FadingIn,
            Working,
            Complete,
            Stopped,
            FadingOut
        }

        private int mTickCount;
        private State mState = State.FadingIn;
        private bool mComplete = false;
        private static int mRecursionCount = 0;

        private void Execute()
        {
            timer.Enabled = true;

            bool run = true;
            Thread.Sleep(DELAY);
            lock (this)
            {
                run = !mComplete;
            }

            if (run)
            {
                Application.Run(this);
            }
        }

        private void timer_Tick(object sender, EventArgs e)
        {
            double opacity;
            switch (mState)
            {
                case State.FadingIn:
                    opacity = Opacity + OPACITY_INCREMENT;
                    if (opacity >= MAX_OPACITY)
                    {
                        Opacity = MAX_OPACITY;
                        mState = State.Working;
                        mTickCount = 0;
                        timer.Interval = 500;
                    }
                    else
                    {
                        Opacity = opacity;
                    }
                    break;

                case State.Working:
                    mTickCount += timer.Interval;
                    if (mTickCount >= MIN_TIME_ON_SCREEN)
                    {
                        bool complete;
                        lock (this)
                        {
                            complete = mComplete;
                            mComplete = false;
                        }

                        if (complete)
                        {
                            mState = State.FadingOut;
                            timer.Interval = 100;
                        }
                    }
                    break;

                case State.FadingOut:
                    opacity = Opacity - OPACITY_DECREMENT;
                    if (opacity <= 0)
                    {
                        Opacity = 0.0;
                        timer.Enabled = false;
                        Close();
                        return;
                    }
                    else
                    {
                        Opacity = opacity;
                    }
                    break;
            }
        }

        public delegate void Operation();

        public static void Execute(Operation operation)
        {
            ++mRecursionCount;
            try
            {
                if (mRecursionCount == 1)
                {
                    Cursor oldCursor = Cursor.Current;
                    Cursor.Current = Cursors.WaitCursor;

                    ProgressDialog dialog = new ProgressDialog();
                    ParameterizedThreadStart threadStart = delegate(object aObject)
                    {
                        ProgressDialog progressDialog = (ProgressDialog)aObject;
                        try
                        {
                            if (Program.Lang != null)
                            {
                                Thread.CurrentThread.CurrentUICulture = new System.Globalization.CultureInfo(Program.Lang);
                            }
                            progressDialog.Execute();
                        }
                        catch (Exception ex)
                        {
                            MessageBox.Show(ex.Message, Properties.Resources.ErrProgress, MessageBoxButtons.OK, MessageBoxIcon.Error);
                        }
                    };

                    Thread thread = new Thread(threadStart);
                    thread.IsBackground = true;
                    thread.Start(dialog);

                    try
                    {
                        operation();
                        Application.DoEvents();
                    }
                    finally
                    {
                        lock (dialog)
                        {
                            dialog.mComplete = true;
                        }
                        Cursor.Current = oldCursor;
                    }
                }
                else
                {
                    operation();
                }
            }
            finally
            {
                --mRecursionCount;
            }
        }

        private ProgressDialog()
        {
            InitializeComponent();
            Opacity = 0.0f;
            TopMost = true;
            timer.Interval = 100;
        }

        private void UserDispose()
        {
            timer.Dispose();
        }
    }
}
