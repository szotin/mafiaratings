using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.Media;

namespace MafiaRatings
{
    public partial class TimerControl : UserControl
    {
        private int mInitialTime;
        private int mPromptTime;
        private DateTime mStartTime;
        private Timer mTimer;

        public TimerControl()
        {
            InitializeComponent();
            Enabled = false;
            mPromptTime = 10;
        }

        private void UserDispose()
        {
            if (mTimer != null)
            {
                mTimer.Dispose();
                mTimer = null;
            }
        }

        private void timer_Tick(object sender, EventArgs e)
        {
            if (mTimer == null)
            {
                return;
            }

            TimeSpan time = DateTime.Now - mStartTime;
            int seconds = mInitialTime - (int)time.TotalSeconds;
            if (seconds <= 0)
            {
                mTimer.Enabled = false;
                resumeButton.ImageIndex = 0;
                textBox.Text = string.Empty;

                try
                {
                    SoundPlayer player = new SoundPlayer(Properties.Resources.end);
                    player.Play();
                }
                catch (Exception)
                {
                }
            }
            else
            {
                try
                {
                    int prevSeconds = Convert.ToInt32(textBox.Text);
                    if (prevSeconds != seconds)
                    {
                        textBox.Text = seconds.ToString();
                        if (mPromptTime > 0 && prevSeconds > mPromptTime && seconds <= mPromptTime)
                        {
                            SoundPlayer player = new SoundPlayer(Properties.Resources.prompt);
                            player.Play();
                        }
                    }
                }
                catch (Exception)
                {
                    textBox.Text = seconds.ToString();
                }
            }
        }

        private void resumeButton_Click(object sender, EventArgs e)
        {
            if (mTimer == null)
            {
                return;
            }

            if (mTimer.Enabled)
            {
                mTimer.Enabled = false;
                resumeButton.ImageIndex = 0;
            }
            else
            {
                try
                {
                    mInitialTime = Convert.ToInt32(textBox.Text);
                }
                catch (Exception)
                {
                    mInitialTime = 0;
                }

                if (mInitialTime > 0)
                {
                    mStartTime = DateTime.Now;
                    mTimer.Enabled = true;
                    resumeButton.ImageIndex = 1;
                }
            }
        }

        public int InitialTime
        {
            get { return mInitialTime; }
            set
            {
                mInitialTime = value;
                textBox.Text = mInitialTime.ToString();
            }
        }

        public int PromptTime
        {
            get { return mPromptTime; }
            set { mPromptTime = value; }
        }

        private void TimerControl_Resize(object sender, EventArgs e)
        {
            int height = ClientRectangle.Height;
            resumeButton.Top = (height - resumeButton.Height) / 2;
            textBox.Top = (height - textBox.Height) / 2;
        }

        private void TimerControl_EnabledChanged(object sender, EventArgs e)
        {
            if (Enabled)
            {
                if (mTimer == null)
                {
                    mTimer = new Timer();
                    mTimer.Interval = 1000;
                    mTimer.Tick += new EventHandler(timer_Tick);
                }
                resumeButton.Visible = true;
                textBox.Visible = true;
            }
            else
            {
                if (mTimer != null)
                {
                    mTimer.Enabled = false;
                    mTimer.Dispose();
                    mTimer = null;
                }

                resumeButton.Visible = false;
                textBox.Visible = false;
            }
            resumeButton.ImageIndex = 0;
        }
    }
}
