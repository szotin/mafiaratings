using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace MafiaRatings.GameObjects
{
    public class GameVoting
    {
        public class Nominant
        {
            private int mPlayerNum;
            private int mNominatedByPlayerNum;
            private int mCount;

            public Nominant(int playerNum, int nominatedByNum)
            {
                mPlayerNum = playerNum;
                mNominatedByPlayerNum = nominatedByNum;
                mCount = 0;
            }

            public Nominant(GameReader reader)
            {
                mPlayerNum = reader.NextInt();
                mNominatedByPlayerNum = reader.NextInt();
                mCount = 0;
            }

            public void Write(GameWriter writer)
            {
                writer.Add(mPlayerNum);
                writer.Add(mNominatedByPlayerNum);
            }

            public void Vote()
            {
                ++mCount;
            }

            public void Unvote()
            {
                --mCount;
            }

            public int PlayerNum
            {
                get { return mPlayerNum; }
            }

            public int NominatedByPlayerNum
            {
                get { return mNominatedByPlayerNum; }
            }

            public int Count
            {
                get { return mCount; }
            }
        }

        private Game mGame;
        private int mRound;
        private List<Nominant> mNominants = new List<Nominant>();
        private int[] mVotes = new int[] { -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 };
        private int mVotingRound;
        private bool mMultipleKill;
        private List<int> mWinners;
        private int mCanceled;

        public GameVoting(Game game, int round)
        {
            mGame = game;
            mRound = round;
            mVotingRound = 0;
            mMultipleKill = false;
            mCanceled = 0;
        }

        public GameVoting(Game game, GameVoting votingToRepeat)
        {
            mGame = game;
            mRound = votingToRepeat.mRound;
            mVotingRound = votingToRepeat.mVotingRound + 1;
            mMultipleKill = false;
            mCanceled = 0;
            foreach (int winner in votingToRepeat.Winners)
            {
                AddNominant(winner, -1);
            }
        }

        public GameVoting(Game game, GameReader reader)
        {
            mGame = game;
            mRound = reader.NextInt();
            mVotingRound = reader.NextInt();
            mMultipleKill = reader.NextBool();
            int canceled = reader.NextInt();
            mCanceled = 0;

            int nominantsCount = reader.NextInt();
            for (int i = 0; i < nominantsCount; ++i)
            {
                mNominants.Add(new Nominant(reader));
            }

            for (int i = 0; i < 10; ++i)
            {
                Vote(i, reader.NextInt());
            }

            mCanceled = canceled;
        }

        public bool AddNominant(int playerNum, int nominatedByPlayerNum)
        {
            if (mCanceled > 0)
            {
                return false;
            }

            GamePlayer player = mGame.Players[playerNum];
            if (player.IsDead || player.IsDummy)
            {
                return false;
            }

            foreach (Nominant nom in mNominants)
            {
                if (nom.PlayerNum == playerNum)
                {
                    return false;
                }
            }
            Nominant nominant = new Nominant(playerNum, nominatedByPlayerNum);
            int index = mNominants.Count;
            mNominants.Add(nominant);
            for (int i = 0; i < 10; ++i)
            {
                Vote(i, index);
            }
            return true;
        }

        public void RemoveNominant(int nominantIndex)
        {
            if (mCanceled > 0)
            {
                return;
            }

            mNominants.RemoveAt(nominantIndex);
            for (int i = 0; i < 10; ++i)
            {
                if (mVotes[i] > nominantIndex)
                {
                    --mVotes[i];
                }
                else if (mVotes[i] == nominantIndex)
                {
                    Vote(i, -1);
                }
            }
        }

        public void Vote(int playerNum, int nominantIndex)
        {
            if (mCanceled > 0)
            {
                return;
            }

            int oldNominant = mVotes[playerNum];
            if (oldNominant >= 0 && oldNominant < mNominants.Count)
            {
                mNominants[oldNominant].Unvote();
            }

            GamePlayer player = mGame.Players[playerNum];
            if (player.IsDead || player.IsDummy)
            {
                mVotes[playerNum] = -1;
            }
            else
            {
                if (nominantIndex < 0 || nominantIndex >= mNominants.Count)
                {
                    nominantIndex = mNominants.Count - 1;
                }
                mVotes[playerNum] = nominantIndex;
                if (nominantIndex >= 0)
                {
                    mNominants[nominantIndex].Vote();
                }
            }
            mWinners = null;
        }

        public void Write(GameWriter writer)
        {
            writer.Add(mRound);
            writer.Add(mVotingRound);
            writer.Add(mMultipleKill);
            writer.Add(mCanceled);

            writer.Add(mNominants.Count);
            foreach (Nominant nom in mNominants)
            {
                nom.Write(writer);
            }

            for (int i = 0; i < 10; ++i)
            {
                writer.Add(mVotes[i]);
            }
        }

        public int GetNominationIndex(int playerNum)
        {
            for (int i = 0; i < mNominants.Count; ++i)
            {
                if (mNominants[i].PlayerNum == playerNum)
                {
                    return i;
                }
            }
            return -1;
        }

        public bool IsNominated(int playerNum)
        {
            return GetNominationIndex(playerNum) >= 0;
        }

        public List<int> Winners
        {
            get
            {
                if (mWinners == null)
                {
                    mWinners = new List<int>(mNominants.Count);

                    int maxCount = 0;
                    foreach (Nominant nominant in mNominants)
                    {
                        if (nominant.Count == 0)
                        {
                            continue;
                        }

                        if (nominant.Count > maxCount)
                        {
                            maxCount = nominant.Count;
                            mWinners.Clear();
                            mWinners.Add(nominant.PlayerNum);
                        }
                        else if (nominant.Count == maxCount)
                        {
                            mWinners.Add(nominant.PlayerNum);
                        }
                    }
                }
                return mWinners;
            }
        }

        public int Round
        {
            get { return mRound; }
        }

        public List<Nominant> Nominants
        {
            get { return mNominants; }
        }

        public int[] Votes
        {
            get { return mVotes; }
        }

        public int VotingRound
        {
            get { return mVotingRound; }
        }

        public bool MultipleKill
        {
            get { return mMultipleKill; }
            set { mMultipleKill = value; }
        }

        public bool Canceled
        {
            get { return mCanceled > 0; }
            set
            {
                if (value)
                {
                    ++mCanceled;
                }
                else if (mCanceled > 0)
                {
                    --mCanceled;
                }
            }
        }
    }
}
