using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.Drawing;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public class GameControl : UserControl
    {
        private bool mInitializing = false;

        public const int DAY = 0;
        public const int NIGHT = 1;

        public const int BACK = 0;
        public const int ALIVE = 1;
        public const int HIGHLIGHT = 2;
        public const int DEAD = 3;
        public const int SYSTEM = 4;

        private static Color[,,] COLOR = 
        {
            {
                { Color.FromArgb(0xaa, 0xaa, 0xaa), Color.FromArgb(0x30, 0x30, 0x30) },
                { Color.FromArgb(0xb6, 0xb6, 0xb6), Color.FromArgb(0x30, 0x30, 0x30) },
                { Color.FromArgb(0xcc, 0xcc, 0xcc), Color.FromArgb(0x30, 0x30, 0x30) },
                { Color.FromArgb(0xaa, 0xaa, 0xaa), Color.FromArgb(0x7e, 0x7e, 0x7e) },
                { Color.FromArgb(0xb6, 0xb6, 0xb6), Color.FromArgb(0x30, 0x30, 0x30) },
            },
            {
                { Color.FromArgb(0x50, 0x50, 0x50), Color.FromArgb(0xcc, 0xcc, 0xcc) },
                { Color.FromArgb(0x46, 0x46, 0x46), Color.FromArgb(0xcc, 0xcc, 0xcc) },
                { Color.FromArgb(0xaa, 0xaa, 0xaa), Color.FromArgb(0x44, 0x44, 0x44) },
                { Color.FromArgb(0x50, 0x50, 0x50), Color.FromArgb(0x8e, 0x8e, 0x8e) },
                { Color.FromArgb(0x46, 0x46, 0x46), Color.FromArgb(0xcc, 0xcc, 0xcc) },
            },
        };

        public GameControl()
        {
            SetColors(this, BACK);
        }

        public virtual bool IsActive
        {
            get { return false; }
        }

        public void Reload()
        {
            mInitializing = true;
            SuspendLayout();
            try
            {
                OnReload();
            }
            finally
            {
                ResumeLayout();
                mInitializing = false;
            }
        }

        public void GameStateChanged()
        {
            mInitializing = true;
            SuspendLayout();
            try
            {
                OnGameStateChange();
            }
            finally
            {
                ResumeLayout();
                mInitializing = false;
            }
        }

        protected virtual void OnReload()
        {
        }

        protected virtual void OnGameStateChange()
        {
        }

        public void SetControlState(Control control, int colors, bool enabled)
        {
            int daytime = Database.Game.IsNight ? NIGHT : DAY;
            control.BackColor = COLOR[daytime, colors, 0];
            control.ForeColor = COLOR[daytime, colors, 1];
            control.Enabled = enabled;
        }

        public static void SetColors(Control control, int colors)
        {
            if (Database.Game != null && Database.Game.IsNight)
            {
                control.BackColor = COLOR[NIGHT, colors, 0];
                control.ForeColor = COLOR[NIGHT, colors, 1];
            }
            else
            {
                control.BackColor = COLOR[DAY, colors, 0];
                control.ForeColor = COLOR[DAY, colors, 1];
            }
        }

        public static Label CreateLabel(string text, ContentAlignment align)
        {
            Label label = new Label();
            label.AutoSize = false;
            label.Text = text;
            label.TextAlign = align;
            label.Dock = DockStyle.Fill;
            return label;
        }

        protected bool Initializing
        {
            get { return mInitializing; }
            set { mInitializing = value; }
        }
    }
}
